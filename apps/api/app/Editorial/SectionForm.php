<?php

namespace App\Editorial;

class SectionForm
{
    public static function fill(array $data): array
    {
        $rows = [];
        $locales = array_unique([config('localization.fallback'), ...array_column(config('localization.locales'), 'code')]);
        foreach ($locales as $locale) {
            $positions = [];
            foreach ($data['translations'][$locale]['sections'] ?? [] as $section) {
                // Legacy lists have no shared identifiers. Pair each group's entries by order.
                $group = $section['group'];
                $position = $positions[$group] ?? 0;
                $positions[$group] = $position + 1;
                $key = $group.':'.$position;
                $rows[$key] ??= ['group' => $group, 'translations' => []];
                $rows[$key]['translations'][$locale] = array_diff_key($section, ['group' => true]);
            }
            unset($data['translations'][$locale]['sections']);
        }
        $data['sections'] = array_values($rows);

        return $data;
    }

    public static function input(array $data): array
    {
        if (! array_key_exists('sections', $data)) {
            return $data;
        }
        foreach (config('localization.locales') as $locale) {
            $code = $locale['code'];
            $data['translations'][$code]['sections'] = [];
            foreach ($data['sections'] as $section) {
                $copy = $section['translations'][$code] ?? [];
                // An empty optional translation does not create an empty public card.
                if (trim($copy['title'] ?? '') === '' && empty($copy['body']) && empty($copy['media_asset_id']) && empty($copy['link']) && empty($copy['icon'])) {
                    continue;
                }
                $data['translations'][$code]['sections'][] = ['group' => $section['group'], ...$copy];
            }
        }
        unset($data['sections']);

        return $data;
    }
}
