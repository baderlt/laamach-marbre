<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MediaAssetResource;
use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\SiteContentResource;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\SiteContent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

class CatalogueOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Product::class);
    }

    protected function getHeading(): ?string
    {
        return __('admin.overview');
    }

    protected function getDescription(): ?string
    {
        return __('admin.overview_help');
    }

    protected function getStats(): array
    {
        $products = Product::selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS drafts, SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published_count, SUM(CASE WHEN viewer_3d_enabled = 1 THEN 1 ELSE 0 END) AS viewers")->first();
        $categories = ProductCategory::selectRaw('COUNT(*) AS total, SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) AS active_count')->first();
        $projects = Project::selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS drafts, SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published_count")->first();
        $media = MediaAsset::selectRaw("COUNT(*) AS total, SUM(CASE WHEN approval_state = 'pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN processing_state = 'failed' THEN 1 ELSE 0 END) AS failed")->first();
        $pages = SiteContent::selectRaw('COUNT(*) AS total, SUM(CASE WHEN published = 1 THEN 1 ELSE 0 END) AS published_count')->first();

        return [
            Stat::make(__('admin.products'), (int) $products->total)->description(__('admin.drafts', ['count' => (int) $products->drafts]).' · '.__('admin.published_count', ['count' => (int) $products->published_count]))->icon('heroicon-o-cube')->url(ProductResource::getUrl()),
            Stat::make(__('admin.categories'), (int) $categories->total)->description(__('admin.active_categories', ['count' => (int) $categories->active_count]))->icon('heroicon-o-squares-2x2')->url(ProductCategoryResource::getUrl()),
            Stat::make(__('admin.projects'), (int) $projects->total)->description(__('admin.drafts', ['count' => (int) $projects->drafts]).' · '.__('admin.published_count', ['count' => (int) $projects->published_count]))->icon('heroicon-o-building-office')->url(ProjectResource::getUrl()),
            Stat::make(__('admin.media'), (int) $media->total)->description(__('admin.media_pending', ['pending' => (int) $media->pending, 'failed' => (int) $media->failed]))->icon('heroicon-o-photo')->url(MediaAssetResource::getUrl()),
            Stat::make(__('admin.pages'), (int) $pages->total)->description(__('admin.pages_published', ['count' => (int) $pages->published_count]))->icon('heroicon-o-document-text')->url(SiteContentResource::getUrl()),
            Stat::make(__('admin.viewers'), (int) $products->viewers)->description(__('admin.viewers_help'))->icon('heroicon-o-viewfinder-circle')->url(ProductResource::getUrl()),
        ];
    }
}
