<?php

namespace App\Catalogue;

use App\Models\CatalogueRecord;
use App\Models\Finish;
use App\Models\MediaAsset;
use App\Models\MediaReference;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogueWriter
{
    public function stageClientDraft(string $slug, string $name, ?string $coverId, array $galleryIds): array
    {
        // Trusted operator CLI only; never an alternate public/admin mutation endpoint.
        abort_unless(app()->runningInConsole(), 403);
        Validator::make(compact('slug', 'name', 'coverId', 'galleryIds'), ['slug' => 'required|string|max:120|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', 'name' => 'required|string|max:160', 'coverId' => 'nullable|uuid', 'galleryIds' => 'array|max:12', 'galleryIds.*' => 'required|uuid|distinct'])->validate();

        return DB::transaction(function () use ($slug, $name, $coverId, $galleryIds) {
            if ($existing = Product::where('slug', $slug)->lockForUpdate()->first()) {
                return ['slug' => $slug, 'result' => 'preserved-existing', 'id' => $existing->id];
            }
            $record = new Product;
            $record->forceFill(['slug' => $slug, 'reference' => 'LM-'.strtoupper(Str::random(12)), 'status' => 'draft', 'category_id' => null, 'cover_media_id' => $coverId])->save();
            $this->translations($record, [config('localization.fallback') => ['name' => $name]]);
            $this->media($record, $galleryIds);

            return ['slug' => $slug, 'result' => 'created-draft', 'id' => $record->id];
        }, 3);
    }

    public function stageConfirmedSizeImage(string $slug, string $sizeId, string $assetId): string
    {
        abort_unless(app()->runningInConsole(), 403);

        return DB::transaction(function () use ($slug, $sizeId, $assetId) {
            $product = Product::where('slug', $slug)->lockForUpdate()->firstOrFail();
            $size = $product->sizes()->whereKey($sizeId)->lockForUpdate()->firstOrFail();
            if ($product->status !== 'draft' || $size->cover_media_id) {
                return 'preserved-existing';
            }
            $size->forceFill(['cover_media_id' => $assetId]);
            $this->media($size, []); // Size media retains M3 approved/published eligibility.
            $size->save();

            return 'assigned-confirmed-size';
        }, 3);
    }

    public function category(?ProductCategory $record, array $input, User $user): ProductCategory
    {
        $this->authorize($record, ProductCategory::class, $user);

        return DB::transaction(function () use ($record, $input) {
            ProductCategory::orderBy('id')->lockForUpdate()->get();
            $data = $this->validate($input + ['active' => false, 'sort_order' => 0, 'parent_id' => null, 'cover_media_id' => null], [
                ...$this->commonRules($record, 'product_categories'), 'parent_id' => 'nullable|uuid|exists:product_categories,id', ...$this->translationRules('category'),
            ]);
            $record ??= new ProductCategory;
            $record->forceFill(collect($data)->except('translations')->all())->save();
            $this->translations($record, $data['translations']);
            $this->media($record, []);

            return $record;
        }, 3);
    }

    public function finish(?Finish $record, array $input, User $user): Finish
    {
        $this->authorize($record, Finish::class, $user);

        return DB::transaction(function () use ($record, $input) {
            if ($record) {
                $record = Finish::whereKey($record->id)->lockForUpdate()->firstOrFail();
            }
            $data = $this->validate($input + ['active' => false, 'sort_order' => 0, 'cover_media_id' => null], [...$this->commonRules($record, 'finishes'), ...$this->translationRules('finish')]);
            $record ??= new Finish;
            $record->forceFill(collect($data)->except('translations')->all())->save();
            $this->translations($record, $data['translations']);
            $this->media($record, []);

            return $record;
        }, 3);
    }

    public function product(?Product $record, array $input, User $user): Product
    {
        $this->authorize($record, Product::class, $user);
        if (($input['status'] ?? 'draft') === 'published') {
            Gate::forUser($user)->authorize('publish', $record ?? Product::class);
        }

        return DB::transaction(function () use ($record, $input) {
            // Same category lock order as hierarchy writes. No product can race a child becoming a root.
            ProductCategory::orderBy('id')->lockForUpdate()->get();
            if ($record) {
                $record = Product::whereKey($record->id)->lockForUpdate()->firstOrFail();
            }
            $published = ($input['status'] ?? 'draft') === 'published';
            $data = $this->validate($input + ['category_id' => null, 'units' => [], 'status' => 'draft', 'featured' => false, 'sort_order' => 0, 'cover_media_id' => null, 'custom_size_allowed' => false, 'custom_measurement_unit' => null, 'min_width' => null, 'max_width' => null, 'min_length' => null, 'max_length' => null, 'sizes' => [], 'finishes' => [], 'gallery' => []], [
                'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique('products')->ignore($record?->id)],
                'category_id' => ($published ? 'required' : 'nullable').'|uuid|exists:product_categories,id',
                'status' => ['required', Rule::in(['draft', 'published'])],
                'featured' => 'required|boolean', 'sort_order' => 'required|integer|min:0|max:100000',
                'cover_media_id' => 'nullable|uuid|exists:media_assets,id',
                'viewer_3d_enabled' => 'sometimes|boolean', 'viewer_3d_config' => 'sometimes|nullable|array',
                'custom_size_allowed' => 'required|boolean',
                'custom_measurement_unit' => ['nullable', Rule::in(config('catalogue.measurement_units'))],
                'min_width' => 'nullable|numeric|between:0.0001,1000000', 'max_width' => 'nullable|numeric|between:0.0001,1000000',
                'min_length' => 'nullable|numeric|between:0.0001,1000000', 'max_length' => 'nullable|numeric|between:0.0001,1000000',
                'units' => ($published ? 'required|array|min:1' : 'array').'|max:4', 'units.*' => ['required', 'distinct', Rule::in(config('catalogue.quantity_units'))],
                'sizes' => 'array|max:40', 'sizes.*' => 'array:id,width,length,height,thickness,measurement_unit,active,sort_order,cover_media_id,translations',
                'sizes.*.id' => 'nullable|uuid|distinct',
                'sizes.*.width' => 'required|numeric|between:0.0001,1000000', 'sizes.*.length' => 'required|numeric|between:0.0001,1000000',
                'sizes.*.height' => 'nullable|numeric|between:0.0001,1000000', 'sizes.*.thickness' => 'nullable|numeric|between:0.0001,1000000',
                'sizes.*.measurement_unit' => ['required', Rule::in(config('catalogue.measurement_units'))],
                'sizes.*.active' => 'required|boolean', 'sizes.*.sort_order' => 'required|integer|min:0|max:100000', 'sizes.*.cover_media_id' => 'nullable|uuid|exists:media_assets,id',
                ...$this->translationRules('size', 'sizes.*.'),
                'finishes' => 'array|max:20', 'finishes.*' => 'array:finish_id,active,is_default,sort_order',
                'finishes.*.finish_id' => 'required|uuid|distinct|exists:finishes,id', 'finishes.*.active' => 'required|boolean', 'finishes.*.is_default' => 'required|boolean', 'finishes.*.sort_order' => 'required|integer|min:0|max:100000',
                'gallery' => 'array|max:12', 'gallery.*' => 'required|uuid|distinct|exists:media_assets,id',
                ...$this->translationRules('product'),
            ]);
            $this->customBounds($data);
            if (count(array_filter($data['finishes'], fn ($item) => $item['is_default'])) > 1 || count(array_filter($data['finishes'], fn ($item) => $item['is_default'] && ! $item['active']))) {
                throw ValidationException::withMessages(['finishes' => 'At most one active default finish is permitted.']);
            }
            $record ??= new Product;
            if (! $record->exists) {
                $record->forceFill(['reference' => 'LM-'.strtoupper(Str::random(12))]);
            }
            $attributes = collect($data)->except(['translations', 'sizes', 'finishes', 'units', 'gallery', 'viewer_3d_enabled', 'viewer_3d_config'])->all();
            if ($data['status'] === 'published') {
                $category = ProductCategory::with('parent')->findOrFail($data['category_id']);
                if (! $category->parent_id || ! $category->active || ! $category->parent->active) {
                    throw ValidationException::withMessages(['category_id' => 'Publication requires an active parent and child.']);
                }
                $attributes['published_at'] = $record->published_at ?? now();
            }
            $record->forceFill($attributes)->save();
            $this->translations($record, $data['translations']);
            $this->media($record, $data['gallery']);
            $keep = [];
            foreach ($data['sizes'] as $sizeData) {
                $size = empty($sizeData['id']) ? new ProductSize : $record->sizes()->whereKey($sizeData['id'])->first();
                if (! $size) {
                    throw ValidationException::withMessages(['sizes' => 'A size cannot be moved or edited across products.']);
                }
                $size->forceFill(['product_id' => $record->id, ...collect($sizeData)->except(['id', 'translations'])->all()])->save();
                $this->translations($size, $sizeData['translations']);
                $this->media($size, []);
                $keep[] = $size->id;
            }
            foreach ($record->sizes()->whereNotIn('id', $keep)->get() as $size) {
                $this->remove($size);
            }
            DB::table('product_units')->where('product_id', $record->id)->delete();
            DB::table('product_units')->insert(array_map(fn ($unit) => ['product_id' => $record->id, 'unit' => $unit], $data['units']));
            $record->finishes()->sync(collect($data['finishes'])->mapWithKeys(fn ($finish) => [$finish['finish_id'] => collect($finish)->except('finish_id')->all()])->all());
            $record->unsetRelation('sizes');
            app(ProductViewer::class)->save($record, $data);

            return $record;
        }, 3);
    }

    public function delete(CatalogueRecord $record, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $record);
        DB::transaction(function () use ($record) {
            ProductCategory::orderBy('id')->lockForUpdate()->get();
            $record = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($record instanceof Product && $record->projects()->exists()) {
                throw ValidationException::withMessages(['record' => 'Product is referenced by a project. Remove the confirmed project assignment first.']);
            }
            if (($record instanceof ProductCategory && ($record->children()->exists() || $record->products()->exists())) || ($record instanceof Finish && $record->products()->exists())) {
                throw ValidationException::withMessages(['record' => 'This record is still referenced.']);
            }
            if ($record instanceof Product) {
                foreach ($record->sizes()->get() as $size) {
                    $this->remove($size);
                }
            }
            $this->remove($record);
        }, 3);
    }

    private function remove(CatalogueRecord $record): void
    {
        $record->mediaReferences()->delete();
        if ($record instanceof Product) {
            $record->viewerReferences()->delete();
        }
        $record->delete();
    }

    private function customBounds(array $data): void
    {
        $fields = ['custom_measurement_unit', 'min_width', 'max_width', 'min_length', 'max_length'];
        if (! $data['custom_size_allowed']) {
            foreach ($fields as $field) {
                if ($data[$field] !== null) {
                    throw ValidationException::withMessages([$field => 'Custom bounds are forbidden when custom size is disabled.']);
                }
            }

            return;
        }
        foreach ($fields as $field) {
            if ($data[$field] === null) {
                throw ValidationException::withMessages([$field => 'Explicit custom width/length bounds and unit are required.']);
            }
        }
        if ($data['min_width'] > $data['max_width'] || $data['min_length'] > $data['max_length']) {
            throw ValidationException::withMessages(['min_width' => 'Minimum dimensions cannot exceed their maximum.']);
        }
    }

    private function media(CatalogueRecord $record, array $gallery): void
    {
        $ids = array_values(array_unique(array_filter([$record->cover_media_id, ...$gallery])));
        $query = MediaAsset::whereIn('id', $ids);
        if ($record instanceof Product && $record->status === 'draft') {
            $query->where('type', 'image')->where('client_supplied', true)->where('processing_state', 'ready');
        } else {
            app(PublicCatalogue::class)->eligibleMedia($query);
        }
        $assets = $query->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($ids as $id) {
            $asset = $assets->get($id);
            if (! $asset) {
                throw ValidationException::withMessages(['cover_media_id' => 'Assign eligible M2 images; private ready client images are permitted only on drafts.']);
            }
        }
        $record->mediaReferences()->delete();
        foreach (array_filter(['cover' => $record->cover_media_id] + collect($gallery)->mapWithKeys(fn ($id, $index) => ['gallery:'.$index => $id])->all()) as $slot => $id) {
            $reference = new MediaReference;
            $reference->forceFill(['media_asset_id' => $id, 'owner_type' => $record->getTable(), 'owner_key' => $record->id, 'slot' => $slot])->save();
        }
    }

    private function translations(CatalogueRecord $record, array $translations): void
    {
        $record->translations()->whereNotIn('locale', array_keys($translations))->delete();
        foreach ($translations as $locale => $copy) {
            $row = $record->translations()->where('locale', $locale)->first() ?? $record->translations()->make();
            $row->forceFill(['locale' => $locale, ...$copy])->save();
        }
    }

    private function authorize(?CatalogueRecord $record, string $class, User $user): void
    {
        Gate::forUser($user)->authorize($record ? 'update' : 'create', $record ?? $class);
    }

    private function commonRules(?CatalogueRecord $record, string $table): array
    {
        return ['slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique($table)->ignore($record?->id)], 'active' => 'required|boolean', 'sort_order' => 'required|integer|min:0|max:100000', 'cover_media_id' => 'nullable|uuid|exists:media_assets,id'];
    }

    private function translationRules(string $type, string $prefix = ''): array
    {
        $label = $type === 'size' ? 'label' : 'name';
        $fields = [$label];
        if ($type !== 'size') {
            $fields[] = 'description';
        }
        if (in_array($type, ['category', 'product'], true)) {
            $fields = [...$fields, 'seo_title', 'seo_description'];
        }
        if ($type === 'product') {
            $fields = [...$fields, 'colour', 'origin', 'technical_information'];
        }
        $base = $prefix.'translations';
        $rules = [$base => 'required|array:'.implode(',', array_column(config('localization.locales'), 'code')), $base.'.*.'.$label => 'required|string|max:160', $base.'.'.config('localization.fallback').'.'.$label => 'required|string|max:160', $base.'.*' => 'array:'.implode(',', $fields)];
        foreach (array_diff($fields, [$label, 'technical_information']) as $field) {
            $rules[$base.'.*.'.$field] = 'nullable|string|max:'.match ($field) {
                'description' => 10000, 'seo_description' => 320, default => 160
            };
        }
        if ($type === 'product') {
            $rules += [$base.'.*.technical_information' => 'nullable|array|max:20', $base.'.*.technical_information.*' => 'array:label,value', $base.'.*.technical_information.*.label' => 'required|string|max:120', $base.'.*.technical_information.*.value' => 'required|string|max:1000'];
        }

        return $rules;
    }

    private function validate(array $data, array $rules): array
    {
        $keys = array_filter(array_keys($rules), fn ($key) => ! str_contains($key, '.'));
        Validator::make(['data' => $data], ['data' => 'required|array:'.implode(',', $keys)])->validate();

        return Validator::make($data, $rules)->validate();
    }
}
