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
use Illuminate\Support\Facades\DB;

class PublicCatalogue
{
    public function homeProducts(string $locale): array
    {
        $public = $this->products($locale);
        $eligible = (clone $public)->select('products.id')->reorder();
        $ranked = DB::table('products')->join('product_categories as home_categories', 'home_categories.id', '=', 'products.category_id')
            ->whereIn('products.id', $eligible)->select('products.id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY home_categories.parent_id ORDER BY products.featured DESC, products.sort_order, products.id) AS home_rank');
        $ids = DB::query()->fromSub($ranked, 'home_products')->where('home_rank', '<=', 3)->select('id');

        return $public->whereIn('products.id', $ids)->get()
            ->sortBy(fn ($product) => [$product->category->parent->sort_order, $product->category->parent_id, $product->featured ? 0 : 1, $product->sort_order, $product->id])
            ->map(fn ($product) => $this->productDto($product, $locale))->values()->all();
    }

    public function groupedProducts(string $locale, int $page = 1): array
    {
        $public = $this->products($locale);
        $ids = (clone $public)->select('products.id')->reorder();
        $products = (clone $public)->reorder()
            ->orderBy(ProductCategory::select('sort_order')->whereColumn('product_categories.id', 'products.category_id'))
            ->orderBy('category_id')->orderBy('sort_order')->orderBy('id')
            ->paginate(config('catalogue.per_page'), ['*'], 'page', $page);
        $categories = $this->categories($locale)->whereNotNull('parent_id')
            ->whereIn('id', $products->getCollection()->pluck('category_id')->unique())
            ->withCount(['products as public_total' => fn ($products) => $products->whereIn('products.id', $ids)])
            ->with('translations')->orderBy('sort_order')->orderBy('id')->get();

        return [
            'data' => $products->getCollection()->map(fn ($product) => $this->productDto($product, $locale))->all(),
            'groups' => $categories->map(fn ($category) => ['category' => $this->categoryDto($category, $locale), 'total' => $category->public_total])->all(),
            'meta' => ['current_page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'per_page' => $products->perPage(), 'total' => $products->total()],
        ];
    }

    public function categoryTree(string $locale): array
    {
        $parents = $this->categories($locale)->whereNull('parent_id')->with(['translations', ...$this->imageRelations('cover'), 'children' => fn ($children) => $children->whereIn('id', $this->categories($locale)->select('id'))->orderBy('sort_order')->orderBy('id'), 'children.translations', ...$this->imageRelations('children.cover')])->orderBy('sort_order')->orderBy('id')->get();

        return $parents->map(fn ($parent) => [...$this->categoryDto($parent, $locale, true), 'children' => $parent->children->map(fn ($child) => $this->categoryDto($child, $locale, true))->all()])->all();
    }

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
            $relations = [...$relations, 'viewerReferences.asset.variants', 'viewerReferences.asset.translations', 'units', ...$this->imageRelations('mediaReferences.asset'), 'sizes' => fn ($sizes) => $sizes->where('active', true)->whereHas('translations', fn ($copy) => $copy->where('locale', $locale)), 'sizes.translations', ...$this->imageRelations('sizes.cover'), 'finishes' => fn ($finishes) => $finishes->whereIn('finishes.id', $this->finishes($locale)->select('id'))->where('product_finishes.active', true), 'finishes.translations', ...$this->imageRelations('finishes.cover')];
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
            'viewer_3d' => app(ProductViewer::class)->public($product, $locale),
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
