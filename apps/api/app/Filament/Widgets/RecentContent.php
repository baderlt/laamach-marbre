<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\SiteContentResource;
use App\Models\Product;
use App\Models\Project;
use App\Models\SiteContent;
use Filament\Widgets\Widget;

class RecentContent extends Widget
{
    protected string $view = 'admin.recent-content';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return CatalogueOverview::canView();
    }

    protected function getViewData(): array
    {
        $updates = collect();
        foreach ([[Product::class, ProductResource::class, 'products'], [Project::class, ProjectResource::class, 'projects'], [SiteContent::class, SiteContentResource::class, 'pages']] as [$model, $resource, $label]) {
            $query = $model::latest('updated_at')->limit(6);
            if ($model !== SiteContent::class) {
                $query->with('translations');
            }
            foreach ($query->get() as $record) {
                $key = $record->slug ?? $record->key;
                $copy = $model === SiteContent::class ? ($record->translations[app()->getLocale()] ?? []) : $record->translations->firstWhere('locale', app()->getLocale());
                $updates->push(['type' => __('admin.'.$label), 'title' => data_get($copy, 'title') ?: data_get($copy, 'name') ?: $key, 'date' => $record->updated_at, 'url' => $resource::getUrl('index', ['tableSearch' => $key])]);
            }
        }

        return ['updates' => $updates->sortByDesc('date')->take(6)];
    }
}
