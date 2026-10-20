<?php

namespace App\Projects;

use App\Catalogue\PublicCatalogue;
use App\Media\PublishedMedia;
use App\Models\CatalogueRecord;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectCategory;

class PublicProjects
{
    public function categoryCards(string $locale): array
    {
        $catalogue = app(PublicCatalogue::class);

        return $this->categories($locale)->with(['translations', ...$catalogue->imageRelations('cover')])->orderBy('sort_order')->orderBy('id')->get()->map(fn ($category) => ['id' => $category->id, 'slug' => $category->slug, ...$this->copy($category, $locale), 'cover' => $catalogue->image($category->cover, $locale)])->all();
    }

    public function assetQuery(?string $type = null)
    {
        return MediaAsset::whereIn('type', $type ? [$type] : ['image', 'video'])->where('client_supplied', true)->where('approval_state', 'approved')->whereNotNull('active_revision')->whereHas('variants', fn ($variants) => $variants->where('state', 'published')->whereIn('disk', ['local_published', 'r2_published'])->whereColumn('publication_revision', 'media_assets.active_revision'));
    }

    public function categories(string $locale)
    {
        $query = ProjectCategory::where('active', true)->whereHas('translations', fn ($copy) => $copy->where('locale', $locale)->where('name', '<>', ''));
        app(PublicCatalogue::class)->safeReferences($query);

        return $query;
    }

    public function projects(string $locale, bool $detail = false)
    {
        $catalogue = app(PublicCatalogue::class);
        $query = Project::where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->whereHas('translations', fn ($copy) => $copy->where('locale', $locale)->where('title', '<>', ''))
            ->whereHas('categories', fn ($categories) => $categories->whereIn('project_categories.id', $this->categories($locale)->select('id')))
            ->where(fn ($project) => $project->whereNull('cover_media_id')->orWhereIn('cover_media_id', $this->assetQuery('image')->select('id')))
            ->whereDoesntHave('mediaReferences', fn ($refs) => $refs->whereNotIn('media_asset_id', $this->assetQuery()->select('id')))
            ->whereDoesntHave('gallery', fn ($items) => $items->where(function ($invalid) {
                $invalid->whereNotIn('type', ['image', 'video'])
                    ->orWhere(fn ($photo) => $photo->where('type', 'image')->whereNotIn('media_asset_id', $this->assetQuery('image')->select('id')))
                    ->orWhere(fn ($video) => $video->where('type', 'video')->whereNotIn('media_asset_id', $this->assetQuery('video')->select('id')))
                    ->orWhere(fn ($poster) => $poster->whereNotNull('poster_media_id')->whereNotIn('poster_media_id', $this->assetQuery('image')->select('id')));
            }));
        $relations = ['translations', 'categories' => fn ($categories) => $categories->whereIn('project_categories.id', $this->categories($locale)->select('id')), 'categories.translations', ...$catalogue->imageRelations('cover')];
        if ($detail) {
            $relations = [...$relations, 'gallery', ...$catalogue->imageRelations('gallery.asset'), ...$catalogue->imageRelations('gallery.poster')];
        }

        return $query->with($relations)->orderBy('sort_order')->orderBy('projects.id');
    }

    public function copy(CatalogueRecord $record, string $locale): array
    {
        return collect(app(PublicCatalogue::class)->copy($record, $locale))->except(['project_id', 'project_category_id'])->all();
    }

    public function card(Project $project, string $locale): array
    {
        $copy = $this->copy($project, $locale);

        return ['reference' => $project->reference, 'slug' => $project->slug, 'title' => $copy['title'], 'summary' => $copy['summary'] ?? null, 'featured' => $project->featured, 'categories' => $project->categories->map(fn ($category) => ['id' => $category->id, 'slug' => $category->slug, 'name' => $this->copy($category, $locale)['name']])->all(), 'cover' => app(PublicCatalogue::class)->image($project->cover, $locale)];
    }

    public function detail(Project $project, string $locale): array
    {
        $catalogue = app(PublicCatalogue::class);
        // Eligibility and the small relation bound are applied before hydrating product cards.
        $products = $catalogue->products($locale)->whereHas('projects', fn ($projects) => $projects->where('projects.id', $project->id))->reorder()->orderByRaw('(SELECT sort_order FROM project_products WHERE project_products.product_id = products.id AND project_products.project_id = ?) ASC', [$project->id])->orderBy('products.id')->limit(config('projects.max_products'))->get();

        return [...$this->card($project, $locale), ...$this->copy($project, $locale), 'location' => $project->location, 'year' => $project->year, 'area' => $project->area, 'area_unit' => $project->area_unit,
            'gallery' => $project->gallery->map(function ($item) use ($locale, $catalogue) {
                return ['type' => $item->type, 'media' => $item->type === 'image' ? $catalogue->image($item->asset, $locale) : ['id' => $item->asset->id, 'duration' => $item->asset->duration, 'variants' => app(PublishedMedia::class)->forAsset($item->asset)], 'caption' => $item->captions[$locale] ?? null, 'poster' => $catalogue->image($item->poster, $locale)];
            })->all(), 'products' => $products->map(fn ($product) => $catalogue->productDto($product, $locale))->all(), 'related_projects' => $this->projects($locale)->where('projects.id', '<>', $project->id)->whereHas('categories', fn ($categories) => $categories->whereIn('project_categories.id', $project->categories->pluck('id')))->limit(4)->get()->map(fn ($related) => $this->card($related, $locale))->all()];
    }

    public function forProduct(Product $product, string $locale): array
    {
        return $this->projects($locale)->whereHas('products', fn ($products) => $products->where('products.id', $product->id))->limit(config('projects.related_projects'))->get()->map(fn ($project) => $this->card($project, $locale))->all();
    }
}
