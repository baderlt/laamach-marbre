<?php

namespace App\Editorial;

use App\Catalogue\PublicCatalogue;
use App\Models\SiteContent;
use App\Projects\PublicProjects;

class PublicSite
{
    public function data(string $locale): array
    {
        $catalogue = app(PublicCatalogue::class);
        $records = SiteContent::where('published', true)->whereDoesntHave('mediaReferences', fn ($refs) => $refs->whereDoesntHave('asset', fn ($asset) => $catalogue->eligibleMedia($asset)))->with($catalogue->imageRelations('mediaReferences.asset'))->get();
        $pages = [];
        $settings = null;
        foreach ($records as $record) {
            $copy = $record->translations[$locale] ?? null;
            $assets = $record->mediaReferences->mapWithKeys(fn ($ref) => [$ref->media_asset_id => $catalogue->image($ref->asset, $locale)]);
            if ($record->key === 'site') {
                $settings = ['tagline' => $copy['tagline'] ?? null, 'address' => $copy['address'] ?? null, 'footer_text' => $copy['footer_text'] ?? null, 'phone' => $record->data['phone'] ?? null, 'email' => $record->data['email'] ?? null, 'map_url' => $record->data['map_url'] ?? null, 'social_links' => $record->data['social_links'] ?? [], 'logo' => $assets->get($record->data['logo_media_id'] ?? '')];
            } elseif ($copy) {
                $pages[$record->key] = collect($copy)->except(['sections', 'tagline', 'address', 'footer_text'])->all() + ['hero' => $assets->get($record->data['hero_media_id'] ?? ''), 'secondary' => $assets->get($record->data['secondary_media_id'] ?? ''), 'sections' => array_map(fn ($section) => collect($section)->except('media_asset_id')->all() + ['media' => $assets->get($section['media_asset_id'] ?? '')], $copy['sections'] ?? [])];
            }
        }
        $projects = app(PublicProjects::class);

        return ['settings' => $settings, 'pages' => (object) $pages, 'featured_products' => $catalogue->products($locale)->where('featured', true)->limit(6)->get()->map(fn ($product) => $catalogue->productDto($product, $locale))->all(), 'featured_projects' => $projects->projects($locale)->where('featured', true)->limit(6)->get()->map(fn ($project) => $projects->card($project, $locale))->all()];
    }
}
