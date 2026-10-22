<?php

namespace App\Editorial;

use App\Catalogue\PublicCatalogue;
use App\Models\SiteContent;
use App\Projects\PublicProjects;

class PublicSite
{
    public function records()
    {
        $catalogue = app(PublicCatalogue::class);

        return SiteContent::where('published', true)->whereDoesntHave('mediaReferences', fn ($refs) => $refs->whereDoesntHave('asset', fn ($asset) => $catalogue->eligibleMedia($asset)));
    }

    public function data(string $locale, bool $navigation = false): array
    {
        $catalogue = app(PublicCatalogue::class);
        $records = $this->records()->with($catalogue->imageRelations('mediaReferences.asset'))->get();
        $pages = [];
        $settings = null;
        foreach ($records as $record) {
            $copy = $record->translations[$locale] ?? null;
            $assets = $record->mediaReferences->mapWithKeys(fn ($ref) => [$ref->media_asset_id => $catalogue->image($ref->asset, $locale)]);
            if ($record->key === 'site') {
                $settings = ['whatsapp' => WhatsAppSettings::publicData($record->data['whatsapp'] ?? []), 'tagline' => $copy['tagline'] ?? null, 'address' => $copy['address'] ?? null, 'footer_text' => $copy['footer_text'] ?? null, 'phone' => $record->data['phone'] ?? null, 'email' => $record->data['email'] ?? null, 'map_url' => $record->data['map_url'] ?? null, 'social_links' => $record->data['social_links'] ?? [], 'additional_contacts' => collect($record->data['additional_contacts'] ?? [])->filter(fn ($contact) => WhatsAppSettings::number($contact['number'] ?? null) !== null)->map(fn ($contact) => ['label' => $contact['label'], 'number' => WhatsAppSettings::number($contact['number']), 'url' => 'https://wa.me/'.WhatsAppSettings::number($contact['number'])])->values()->all(), 'logo' => $assets->get($record->data['logo_media_id'] ?? '')];
            } elseif ($copy) {
                $pages[$record->key] = collect($copy)->except(['sections', 'tagline', 'address', 'footer_text'])->all() + ['available_locales' => array_values(array_filter(array_column(config('localization.locales'), 'code'), fn ($code) => filled($record->translations[$code]['title'] ?? null))), 'hero' => $assets->get($record->data['hero_media_id'] ?? ''), 'secondary' => $assets->get($record->data['secondary_media_id'] ?? ''), 'sections' => array_map(fn ($section) => collect($section)->except('media_asset_id')->all() + ['media' => $assets->get($section['media_asset_id'] ?? '')], $copy['sections'] ?? [])];
            }
        }
        $projects = app(PublicProjects::class);

        return ['settings' => $settings, 'pages' => (object) $pages, 'featured_products' => $catalogue->products($locale)->where('featured', true)->limit(6)->get()->map(fn ($product) => $catalogue->productDto($product, $locale))->all(), 'featured_projects' => $projects->projects($locale)->where('featured', true)->limit(6)->get()->map(fn ($project) => $projects->card($project, $locale))->all(), ...($navigation ? ['home_products' => $catalogue->homeProducts($locale), 'navigation' => ['products' => empty(array_filter($pages['home']['sections'] ?? [], fn ($section) => ($section['group'] ?? '') === 'services')) ? $catalogue->categoryTree($locale) : [], 'projects' => $projects->categoryCards($locale)]] : [])];
    }
}
