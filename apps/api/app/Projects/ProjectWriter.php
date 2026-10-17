<?php

namespace App\Projects;

use App\Models\CatalogueRecord;
use App\Models\MediaAsset;
use App\Models\MediaReference;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectWriter
{
    public function save(?CatalogueRecord $record, array $input, User $user, bool $category = false): CatalogueRecord
    {
        $class = $category ? ProjectCategory::class : Project::class;
        Gate::forUser($user)->authorize($record ? 'update' : 'create', $record ?? $class);
        if (! $category && ($input['status'] ?? 'draft') === 'published') {
            Gate::forUser($user)->authorize('publish', $record ?? $class);
        }

        return DB::transaction(function () use ($record, $input, $category, $class) {
            if ($record) {
                $record = $class::whereKey($record->id)->lockForUpdate()->firstOrFail();
            }
            $data = $input + ['sort_order' => 0, 'cover_media_id' => null] + ($category ? ['active' => false] : ['status' => 'draft', 'featured' => false, 'year' => null, 'location' => null, 'area' => null, 'area_unit' => null, 'categories' => [], 'products' => [], 'gallery' => []]);
            $rules = ['slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique($category ? 'project_categories' : 'projects')->ignore($record?->id)], 'sort_order' => 'required|integer|between:0,100000', 'cover_media_id' => 'nullable|uuid|exists:media_assets,id', ...$this->translationRules($category)];
            if ($category) {
                $rules['active'] = 'required|boolean';
            } else {
                $rules += ['status' => ['required', Rule::in(['draft', 'published'])], 'featured' => 'required|boolean', 'year' => 'nullable|integer|between:1900,2100', 'location' => 'nullable|string|max:200', 'area' => 'nullable|numeric|between:0.01,1000000000', 'area_unit' => ['nullable', Rule::in(config('projects.area_units'))],
                    'categories' => 'array|max:'.config('projects.max_categories'), 'categories.*' => 'required|uuid|distinct|exists:project_categories,id',
                    'products' => 'array|max:'.config('projects.max_products'), 'products.*' => 'array:product_id,sort_order', 'products.*.product_id' => 'required|uuid|distinct|exists:products,id', 'products.*.sort_order' => 'required|integer|between:0,100000',
                    'gallery' => 'array|max:'.config('projects.max_gallery'), 'gallery.*' => 'array:media_asset_id,type,poster_media_id,captions', 'gallery.*.media_asset_id' => 'required|uuid|distinct|exists:media_assets,id', 'gallery.*.type' => ['required', Rule::in(['image', 'video'])], 'gallery.*.poster_media_id' => 'nullable|uuid|exists:media_assets,id', 'gallery.*.captions' => 'sometimes|array:'.implode(',', array_column(config('localization.locales'), 'code')), 'gallery.*.captions.*' => 'nullable|string|max:500',
                ];
            }
            Validator::make(['data' => $data], ['data' => 'array:'.implode(',', array_filter(array_keys($rules), fn ($key) => ! str_contains($key, '.')))])->validate();
            $data = Validator::make($data, $rules)->validate();
            if (! $category) {
                if (($data['area'] === null) !== ($data['area_unit'] === null)) {
                    throw ValidationException::withMessages(['area_unit' => 'Area and its explicit unit must be supplied together.']);
                }
                $old = $record?->categories()->pluck('project_categories.id')->all() ?? [];
                $categories = ProjectCategory::whereIn('id', $data['categories'])->orderBy('id')->lockForUpdate()->get();
                foreach ($categories as $item) {
                    if (! $item->active && ! in_array($item->id, $old, true)) {
                        throw ValidationException::withMessages(['categories' => 'New assignments require active project categories.']);
                    }
                }
                // Lock confirmed material assignments against concurrent deletion.
                Product::whereIn('id', array_column($data['products'], 'product_id'))->orderBy('id')->lockForUpdate()->get();
                if ($data['status'] === 'published' && ! app(PublicProjects::class)->categories(config('localization.fallback'))->whereIn('id', $data['categories'])->exists()) {
                    throw ValidationException::withMessages(['categories' => 'Publication requires at least one active, eligible project category.']);
                }
            }
            $record ??= new $class;
            if (! $category && ! $record->exists) {
                $record->forceFill(['reference' => 'LP-'.strtoupper(Str::random(12))]);
            }
            $attributes = collect($data)->except(['translations', 'categories', 'products', 'gallery'])->all();
            if (! $category && $data['status'] === 'published') {
                $attributes['published_at'] = $record->published_at ?? now();
            }
            $record->forceFill($attributes)->save();
            $record->translations()->whereNotIn('locale', array_keys($data['translations']))->delete();
            foreach ($data['translations'] as $locale => $copy) {
                $row = $record->translations()->where('locale', $locale)->first() ?? $record->translations()->make();
                $row->forceFill(['locale' => $locale, ...$copy])->save();
            }
            $roles = ['cover' => [$record->cover_media_id, 'image']];
            if (! $category) {
                $record->categories()->sync($data['categories']);
                $record->products()->sync(collect($data['products'])->mapWithKeys(fn ($item) => [$item['product_id'] => ['sort_order' => $item['sort_order']]])->all());
                $record->gallery()->delete();
                foreach ($data['gallery'] as $index => $item) {
                    if ($item['type'] !== 'video' && ! empty($item['poster_media_id'])) {
                        throw ValidationException::withMessages(['gallery' => 'Only videos accept poster images.']);
                    }
                    $row = $record->gallery()->make();
                    $row->forceFill([...$item, 'captions' => $item['captions'] ?? [], 'sort_order' => $index])->save();
                    $roles['gallery:'.$index] = [$item['media_asset_id'], $item['type']];
                    $roles['poster:'.$index] = [$item['poster_media_id'] ?? null, 'image'];
                }
            }
            $this->media($record, $roles);

            return $record;
        }, 3);
    }

    private function media(CatalogueRecord $record, array $roles): void
    {
        $assets = MediaAsset::whereIn('id', array_filter(array_column($roles, 0)))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($roles as [$id, $type]) {
            if (! $id) {
                continue;
            }
            $asset = $assets->get($id);
            if (! $asset || $asset->type !== $type || ! app(PublicProjects::class)->assetQuery($type)->whereKey($id)->exists()) {
                throw ValidationException::withMessages(['gallery' => 'Assign only approved published media matching its photo/video role.']);
            }
        }
        $record->mediaReferences()->delete();
        foreach ($roles as $slot => [$id, $type]) {
            if (! $id) {
                continue;
            }
            $reference = new MediaReference;
            $reference->forceFill(['media_asset_id' => $id, 'owner_type' => $record->getTable(), 'owner_key' => $record->id, 'slot' => $slot])->save();
        }
    }

    public function delete(CatalogueRecord $record, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $record);
        DB::transaction(function () use ($record) {
            $record = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($record instanceof ProjectCategory && $record->projects()->exists()) {
                throw ValidationException::withMessages(['record' => 'Category is referenced by projects.']);
            }
            $record->mediaReferences()->delete();
            $record->delete();
        }, 3);
    }

    private function translationRules(bool $category): array
    {
        $name = $category ? 'name' : 'title';
        $fields = [$name, 'description', 'seo_title', 'seo_description', ...($category ? [] : ['summary', 'area_display'])];
        $rules = ['translations' => 'required|array:'.implode(',', array_column(config('localization.locales'), 'code')), 'translations.*' => 'array:'.implode(',', $fields), 'translations.*.'.$name => 'required|string|max:160', 'translations.'.config('localization.fallback').'.'.$name => 'required|string|max:160'];
        foreach (array_diff($fields, [$name]) as $field) {
            $rules['translations.*.'.$field] = 'nullable|string|max:'.match ($field) {
                'description' => 10000, 'seo_description' => 320, 'summary' => 500, 'area_display' => 200, default => 160
            };
        }

        return $rules;
    }
}
