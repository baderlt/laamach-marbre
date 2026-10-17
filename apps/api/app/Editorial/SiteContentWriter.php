<?php

namespace App\Editorial;

use App\Catalogue\PublicCatalogue;
use App\Models\MediaAsset;
use App\Models\MediaReference;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiteContentWriter
{
    public function save(?SiteContent $record, array $input, User $user): SiteContent
    {
        Gate::forUser($user)->authorize($record ? 'update' : 'create', $record ?? SiteContent::class);
        if ($input['published'] ?? false) {
            Gate::forUser($user)->authorize('publish', $record ?? SiteContent::class);
        }
        $locales = implode(',', array_column(config('localization.locales'), 'code'));
        $rules = ['input' => 'array:key,published,translations,data', 'input.key' => ['required', Rule::in(SiteContent::KEYS), Rule::unique('site_contents', 'key')->ignore($record?->key, 'key')], 'input.published' => 'required|boolean', 'input.translations' => 'present|array:'.$locales, 'input.translations.*' => 'array:title,eyebrow,body,seo_title,seo_description,tagline,address,footer_text,sections,cta_title,cta_body', 'input.data' => 'present|array:hero_media_id,secondary_media_id,logo_media_id,phone,email,map_url,social_links'];
        foreach (['title' => 180, 'eyebrow' => 120, 'body' => 10000, 'seo_title' => 160, 'seo_description' => 320, 'tagline' => 200, 'address' => 500, 'footer_text' => 1000, 'cta_title' => 180, 'cta_body' => 500] as $key => $max) {
            $rules['input.translations.*.'.$key] = 'nullable|string|max:'.$max;
        }
        $rules += ['input.translations.*.sections' => 'sometimes|array|max:16', 'input.translations.*.sections.*' => 'array:group,title,body,icon,media_asset_id,link', 'input.translations.*.sections.*.group' => ['required', Rule::in(['activities', 'services', 'trust', 'process', 'story'])], 'input.translations.*.sections.*.title' => 'required|string|max:180', 'input.translations.*.sections.*.body' => 'nullable|string|max:2000', 'input.translations.*.sections.*.icon' => ['nullable', Rule::in(['stone', 'building', 'ruler', 'craft', 'truck', 'check', 'leaf', 'spark'])], 'input.translations.*.sections.*.link' => ['nullable', Rule::in(['products', 'projects', 'services', 'about', 'contact'])], 'input.translations.*.sections.*.media_asset_id' => 'nullable|uuid|exists:media_assets,id', 'input.data.phone' => 'nullable|string|max:40|regex:/^[+0-9 ()-]+$/D', 'input.data.email' => 'nullable|email:rfc|max:254', 'input.data.map_url' => 'nullable|url:https|max:1000', 'input.data.social_links' => 'sometimes|array|max:6', 'input.data.social_links.*' => 'array:label,url', 'input.data.social_links.*.label' => 'required|string|max:40', 'input.data.social_links.*.url' => 'required|url:https|max:500'];
        foreach (['hero_media_id', 'secondary_media_id', 'logo_media_id'] as $key) {
            $rules['input.data.'.$key] = 'nullable|uuid|exists:media_assets,id';
        }
        $data = Validator::make(['input' => $input + ['published' => false, 'translations' => [], 'data' => []]], $rules)->validate()['input'];
        if ($record && $record->key !== $data['key']) {
            throw ValidationException::withMessages(['key' => 'Editorial page keys are immutable.']);
        }
        if ($data['published'] && $data['key'] !== 'site' && empty($data['translations'][config('localization.fallback')]['title'])) {
            throw ValidationException::withMessages(['translations' => 'Publication requires a fallback-language title.']);
        }
        foreach ([...($data['data']['social_links'] ?? []), ...(! empty($data['data']['map_url']) ? [['url' => $data['data']['map_url']]] : [])] as $link) {
            if (parse_url($link['url'], PHP_URL_USER) !== null || parse_url($link['url'], PHP_URL_PASS) !== null) {
                throw ValidationException::withMessages(['data' => 'URLs may not contain credentials.']);
            }
        }
        foreach ($data['data']['social_links'] ?? [] as $link) {
            $host = strtolower(parse_url($link['url'], PHP_URL_HOST) ?? '');
            if (! in_array(preg_replace('/^www\./', '', $host), ['facebook.com', 'instagram.com', 'youtube.com', 'linkedin.com'], true)) {
                throw ValidationException::withMessages(['data.social_links' => 'Use supported public social profiles.']);
            }
        }

        return DB::transaction(function () use ($record, $data) {
            if ($record) {
                $record = SiteContent::whereKey($record->key)->lockForUpdate()->firstOrFail();
            }
            $roles = [];
            foreach (['hero_media_id', 'secondary_media_id', 'logo_media_id'] as $key) {
                if ($id = $data['data'][$key] ?? null) {
                    $roles[$key] = $id;
                }
            }
            foreach ($data['translations'] as $locale => $copy) {
                foreach ($copy['sections'] ?? [] as $index => $section) {
                    if ($id = $section['media_asset_id'] ?? null) {
                        $roles[$locale.':section:'.$index] = $id;
                    }
                }
            }
            $query = MediaAsset::whereIn('id', array_values($roles));
            app(PublicCatalogue::class)->eligibleMedia($query);
            $assets = $query->orderBy('id')->lockForUpdate()->pluck('id')->all();
            if (array_diff(array_values($roles), $assets)) {
                throw ValidationException::withMessages(['data' => 'Use only approved published client-supplied M2 images.']);
            }
            $record ??= new SiteContent;
            $record->forceFill($data)->save();
            $record->mediaReferences()->delete();
            foreach ($roles as $slot => $id) {
                $ref = new MediaReference;
                $ref->forceFill(['owner_type' => 'site_contents', 'owner_key' => $record->key, 'slot' => $slot, 'media_asset_id' => $id])->save();
            }

            return $record;
        }, 3);
    }
}
