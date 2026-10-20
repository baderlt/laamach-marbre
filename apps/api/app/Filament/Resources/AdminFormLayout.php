<?php

namespace App\Filament\Resources;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Lang;

class AdminFormLayout
{
    public static function label($component)
    {
        if ($component instanceof Field) {
            $name = $component->getName();
            $key = 'admin.field_'.last(explode('.', $name));
            if (Lang::has($key)) {
                $suffix = '';
                if (preg_match('/^translations\.(fr|ar)\./', $name, $locale)) {
                    $suffix = ' · '.($locale[1] === 'fr' ? 'Français' : 'العربية');
                    $component->extraAttributes(['dir' => $locale[1] === 'ar' ? 'rtl' : 'ltr']);
                }
                $component->label(__($key).$suffix);
            }
            if ($component instanceof Repeater) {
                $component->schema(array_map(self::label(...), $component->getDefaultChildComponents()));
                $component->reorderableWithButtons();
            }
            if ($component instanceof Select && $name === 'status') {
                $component->options(['draft' => __('admin.draft'), 'published' => __('admin.published')])->disableOptionWhen(fn ($value) => $value === 'published' && ! auth()->user()?->isSuperAdmin());
            }
            if ($component instanceof Select && $name === 'units') {
                $component->options(collect(config('catalogue.quantity_units'))->mapWithKeys(fn ($unit) => [$unit => $unit === 'm2' ? 'm²' : __('admin.unit_'.$unit)])->all());
            }
        }

        return $component;
    }

    public static function group(array $fields): array
    {
        $basic = $media = $dimensions = $advanced = $sections = $translations = [];
        foreach ($fields as $component) {
            $component = self::label($component);
            if (! $component instanceof Field) {
                if ($component instanceof Image) {
                    $media[] = $component;
                } else {
                    $advanced[] = $component;
                }

                continue;
            }
            $name = $component->getName();
            if (preg_match('/^translations\.(fr|ar)\./', $name, $match)) {
                $component->extraAttributes(['dir' => $match[1] === 'ar' ? 'rtl' : 'ltr']);
                $translations[$match[1]][] = $component;
            } elseif (in_array($name, ['cover_media_id', 'gallery'], true) || str_ends_with($name, '_media_id')) {
                $media[] = $component;
            } elseif (in_array($name, ['sizes', 'finishes', 'custom_size_allowed', 'custom_measurement_unit', 'min_width', 'max_width', 'min_length', 'max_length'], true)) {
                $dimensions[] = $component;
            } elseif ($name === 'sections') {
                $sections[] = $component;
            } elseif (str_starts_with($name, 'data.')) {
                $advanced[] = $component;
            } else {
                $basic[] = $component;
            }
        }
        $result = [];
        if ($basic) {
            $result[] = Section::make(__('admin.details'))->schema($basic)->columns(['default' => 1, 'md' => 2]);
        }
        if ($translations) {
            $tabs = [];
            foreach (config('localization.locales') as $locale) {
                $content = $seo = [];
                foreach ($translations[$locale['code']] ?? [] as $field) {
                    if (str_contains($field->getName(), '.seo_')) {
                        $seo[] = $field;
                    } else {
                        $content[] = $field;
                    }
                }
                if ($seo) {
                    $content[] = Section::make(__('admin.seo'))->schema($seo)->collapsible()->collapsed();
                }
                $tabs[] = Tab::make($locale['name'])->schema($content)->extraAttributes(['dir' => $locale['direction']]);
            }
            $result[] = Tabs::make(__('admin.translations'))->tabs($tabs);
        }
        foreach ([['images', $media], ['sections', $sections], ['dimensions', $dimensions], ['advanced', $advanced]] as [$title, $components]) {
            if ($components) {
                $result[] = Section::make(__('admin.'.$title))->schema($components)->collapsible()->collapsed($title === 'advanced');
            }
        }

        return $result;
    }
}
