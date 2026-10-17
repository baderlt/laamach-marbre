<?php

namespace App\Catalogue;

use App\Media\PublishedMedia;
use App\Models\CatalogueRecord;
use App\Models\Finish;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Projects\PublicProjects;
use Illuminate\Database\Eloquent\Builder;

class PublicCatalogue
{
    public function imageRelations(string $path): array
    {
        return [$path.'.translations', $path.'.variants' => fn ($query) => $query->where('state', 'published')->whereHas('asset', fn ($asset) => $asset->whereColumn('media_assets.active_revision', 'media_variants.publication_revision'))];
    }

    public function eligibleMedia(Builder $query): void
    {
        $query->where('type', 'image')->where('client_supplied', true)->where('approval_state', 'approved')->whereNotNull('active_revision')
            ->whereHas('variants', fn ($variants) => $variants->where('state', 'published')->whereIn('disk', ['local_published', 'r2_published'])->whereColumn('publication_revision', 'media_assets.active_revision'));
    }

    public function translated(Builder $query, string $locale): void
    {
        $query->whereHas('translations', fn ($copy) => $copy->where('locale', $locale)->where('name', '<>', ''));
    }

    public function safeReferences(Builder $query): void
    {
        $query->where(fn ($owner) => $owner->whereNull('cover_media_id')->orWhereHas('cover', fn ($asset) => $this->eligibleMedia($asset)));
        $query->whereDoesntHave('mediaReferences', fn ($refs) => $refs->whereDoesntHave('asset', fn ($asset) => $this->eligibleMedia($asset)));
    }

    public function categories(string $locale): Builder
    {
        $query = ProductCategory::query()->where('active', true);
        $this->translated($query, $locale);
        $this->safeReferences($query);

        return $query;
    }

    public function finishes(string $locale): Builder
    {
        $query = Finish::query()->where('active', true);
        $this->translated($query, $locale);
        $this->safeReferences($query);

        return $query;
    }

    public function products(string $locale, bool $detail = false): Builder
    {
        $query = Product::query()->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
        $query->whereHas('units', fn ($units) => $units->whereIn('unit', config('catalogue.quantity_units')))
            ->whereDoesntHave('units', fn ($units) => $units->whereNotIn('unit', config('catalogue.quantity_units')))
            ->where(fn ($custom) => $custom->where('custom_size_allowed', false)->orWhereIn('custom_measurement_unit', config('catalogue.measurement_units')))
            ->whereDoesntHave('sizes', fn ($size) => $size->where('active', true)->whereNotIn('measurement_unit', config('catalogue.measurement_units')))
            ->whereDoesntHave('sizes', fn ($size) => $size->where('active', true)->whereNotNull('cover_media_id')->whereDoesntHave('cover', fn ($asset) => $this->eligibleMedia($asset)));
        $this->translated($query, $locale);
        $this->safeReferences($query);
        $query->whereHas('category', function ($child) use ($locale) {
            $child->whereIn('id', $this->categories($locale)->select('id'))->whereHas('parent', fn ($parent) => $parent->whereIn('id', $this->categories($locale)->whereNull('parent_id')->select('id')));
        })->whereDoesntHave('sizes', fn ($size) => $size->where('active', true)->whereHas('mediaReferences', fn ($refs) => $refs->whereDoesntHave('asset', fn ($asset) => $this->eligibleMedia($asset))));
        $relations = ['translations', 'category.translations', 'category.parent.translations', ...$this->imageRelations('cover')];
        if ($detail) {
            $relations = [...$relations, 'units', ...$this->imageRelations('mediaReferences.asset'), 'sizes' => fn ($sizes) => $sizes->where('active', true)->whereHas('translations', fn ($copy) => $copy->where('locale', $locale)), 'sizes.translations', ...$this->imageRelations('sizes.cover'), 'finishes' => fn ($finishes) => $finishes->whereIn('finishes.id', $this->finishes($locale)->select('id'))->where('product_finishes.active', true), 'finishes.translations', ...$this->imageRelations('finishes.cover')];
        }

        return $query->with($relations)->orderBy('sort_order')->orderBy('id');
    }

    public function copy(CatalogueRecord $record, string $locale): array
    {
        $translation = $record->translations->firstWhere('locale', $locale);

        return $translation ? collect($translation->attributesToArray())->except(['id', 'locale', 'created_at', 'updated_at', 'product_id', 'product_category_id', 'product_size_id', 'finish_id'])->all() : [];
    }

    public function image(?MediaAsset $asset, string $locale): ?array
    {
        if (! $asset || ! $asset->client_supplied) {
            return null;
        }
        $variants = app(PublishedMedia::class)->forAsset($asset);
        if (! $variants) {
            return null;
        }
        usort($variants, fn ($a, $b) => $a['width'] <=> $b['width']);

        return ['id' => $asset->id, 'alt' => $asset->translations->firstWhere('locale', $locale)?->alt ?? '', 'variants' => $variants];
    }

    public function categoryDto(ProductCategory $category, string $locale, bool $cover = false): array
    {
        return ['id' => $category->id, 'slug' => $category->slug, ...$this->copy($category, $locale), ...($cover ? ['cover' => $this->image($category->cover, $locale)] : [])];
    }

    public function productDto(Product $product, string $locale, bool $detail = false): array
    {
        $copy = $this->copy($product, $locale);
        $base = ['reference' => $product->reference, 'slug' => $product->slug, 'name' => $copy['name'], 'featured' => $product->featured, 'parent_category' => $this->categoryDto($product->category->parent, $locale), 'category' => $this->categoryDto($product->category, $locale), 'cover' => $this->image($product->cover, $locale)];
        if (! $detail) {
            return $base;
        }

        return [...$base, ...$copy,
            'projects' => app(PublicProjects::class)->forProduct($product, $locale),
            'gallery' => $product->mediaReferences->filter(fn ($ref) => str_starts_with($ref->slot, 'gallery:'))->sortBy(fn ($ref) => (int) substr($ref->slot, 8))->map(fn ($ref) => $this->image($ref->asset, $locale))->filter()->values()->all(),
            'sizes' => $product->sizes->map(fn ($size) => ['id' => $size->id, ...$this->copy($size, $locale), 'width' => $size->width, 'length' => $size->length, 'height' => $size->height, 'thickness' => $size->thickness, 'measurement_unit' => $size->measurement_unit, 'cover' => $this->image($size->cover, $locale)])->all(),
            'custom_size_allowed' => $product->custom_size_allowed,
            'custom_size' => $product->custom_size_allowed ? ['measurement_unit' => $product->custom_measurement_unit, 'min_width' => $product->min_width, 'max_width' => $product->max_width, 'min_length' => $product->min_length, 'max_length' => $product->max_length] : null,
            'quantity_units' => $product->units->pluck('unit')->all(),
            'finishes' => $product->finishes->map(fn ($finish) => ['id' => $finish->id, 'slug' => $finish->slug, ...$this->copy($finish, $locale), 'is_default' => (bool) $finish->pivot->is_default, 'cover' => $this->image($finish->cover, $locale)])->all(),
        ];
    }
}
