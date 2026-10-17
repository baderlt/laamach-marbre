<?php

namespace App\Filament\Resources;

use App\Catalogue\PublicCatalogue;
use App\Models\CatalogueRecord;
use App\Models\Finish;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Arr;

class CatalogueForms
{
    public static function fields(string $kind): array
    {
        $fields = [TextInput::make('slug')->required()->maxLength(120)->helperText('Explicit stable URL slug. Published product slugs are locked.'), TextInput::make('sort_order')->integer()->minValue(0)->maxValue(100000)->default(0)->required()];
        if ($kind !== 'product') {
            $fields[] = Toggle::make('active')->default(false);
        }
        if ($kind === 'category') {
            $fields[] = Select::make('parent_id')->label('Parent (empty = root)')->options(fn () => ProductCategory::whereNull('parent_id')->orderBy('slug')->pluck('slug', 'id'))->searchable();
        }
        if ($kind === 'product') {
            $fields = [...$fields, Select::make('category_id')->label('Child category')->options(fn () => ProductCategory::whereNotNull('parent_id')->orderBy('slug')->pluck('slug', 'id'))->required()->searchable(), Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published (super-admin)'])->default('draft')->required(), Toggle::make('featured')->default(false)];
        }
        $fields[] = self::image('cover_media_id', 'Approved published cover');
        $fields = [...$fields, ...self::translations($kind)];
        if ($kind === 'product') {
            $fields[] = Select::make('units')->label('Permitted quotation quantity units')->multiple()->options(array_combine(config('catalogue.quantity_units'), config('catalogue.quantity_units')))->required();
            $fields[] = Toggle::make('custom_size_allowed')->label('Custom dimensions on request')->default(false)->live()->afterStateUpdated(function ($state, Set $set) {
                if (! $state) {
                    foreach (['custom_measurement_unit', 'min_width', 'max_width', 'min_length', 'max_length'] as $key) {
                        $set($key, null);
                    }
                }
            });
            $fields[] = Select::make('custom_measurement_unit')->options(array_combine(config('catalogue.measurement_units'), config('catalogue.measurement_units')))->visible(fn (Get $get) => (bool) $get('custom_size_allowed'))->dehydratedWhenHidden();
            foreach (['min_width', 'max_width', 'min_length', 'max_length'] as $key) {
                $fields[] = TextInput::make($key)->numeric()->minValue(0.0001)->maxValue(1000000)->visible(fn (Get $get) => (bool) $get('custom_size_allowed'))->dehydratedWhenHidden();
            }
            $sizeFields = [Hidden::make('id')];
            foreach (['width', 'length', 'height', 'thickness'] as $dimension) {
                $sizeFields[] = TextInput::make($dimension)->numeric()->minValue(0.0001)->maxValue(1000000)->required(in_array($dimension, ['width', 'length'], true));
            }
            $sizeFields = [...$sizeFields, Select::make('measurement_unit')->options(array_combine(config('catalogue.measurement_units'), config('catalogue.measurement_units')))->required(), Toggle::make('active')->default(true), TextInput::make('sort_order')->integer()->minValue(0)->default(0)->required(), self::image('cover_media_id', 'Optional size cover'), ...self::translations('size')];
            $fields[] = Repeater::make('sizes')->schema($sizeFields)->maxItems(40)->defaultItems(0)->reorderable(false)->helperText('Explicit measurements. Active means selectable, not guaranteed inventory. Ordering uses sort_order.');
            $fields[] = Repeater::make('finishes')->schema([Select::make('finish_id')->options(fn () => Finish::orderBy('slug')->pluck('slug', 'id'))->required()->searchable(), Toggle::make('active')->default(true), Toggle::make('is_default')->default(false), TextInput::make('sort_order')->integer()->minValue(0)->default(0)->required()])->maxItems(20)->defaultItems(0)->reorderable(false);
            $fields[] = Repeater::make('gallery')->schema([self::image('media_asset_id', 'Approved published gallery image')->required()])->maxItems(12)->defaultItems(0)->helperText('Drag to order. No raw storage keys.');
        }

        return $fields;
    }

    private static function translations(string $kind): array
    {
        $fields = [];
        foreach (config('localization.locales') as $locale) {
            $prefix = 'translations.'.$locale['code'].'.';
            $label = $kind === 'size' ? 'label' : 'name';
            $fields[] = TextInput::make($prefix.$label)->label($locale['name'].' '.$label)->maxLength(160)->required($locale['code'] === config('localization.fallback'));
            if ($kind !== 'size') {
                $fields[] = Textarea::make($prefix.'description')->label($locale['name'].' description')->maxLength(10000);
            }
            if (in_array($kind, ['product', 'category'], true)) {
                $fields[] = TextInput::make($prefix.'seo_title')->label($locale['name'].' SEO title')->maxLength(160);
                $fields[] = Textarea::make($prefix.'seo_description')->label($locale['name'].' SEO description')->maxLength(320);
            }
            if ($kind === 'product') {
                foreach (['colour', 'origin'] as $key) {
                    $fields[] = TextInput::make($prefix.$key)->label($locale['name'].' '.$key)->maxLength(160);
                }
                $fields[] = Repeater::make($prefix.'technical_information')->label($locale['name'].' technical information')->schema([TextInput::make('label')->required()->maxLength(120), TextInput::make('value')->required()->maxLength(1000)])->maxItems(20)->defaultItems(0);
            }
        }

        return $fields;
    }

    private static function image(string $key, string $label): Select
    {
        return Select::make($key)->label($label)->searchable()->getSearchResultsUsing(function (string $search) {
            $query = MediaAsset::query();
            app(PublicCatalogue::class)->eligibleMedia($query);

            return $query->where('original_filename', 'like', '%'.addcslashes($search, '%_\\').'%')->limit(50)->pluck('original_filename', 'id')->all();
        })->getOptionLabelUsing(fn ($value) => MediaAsset::find($value)?->original_filename);
    }

    public static function data(CatalogueRecord $record): array
    {
        $data = collect($record->getAttributes())->except(['id', 'reference', 'created_at', 'updated_at', 'published_at'])->all();
        $data['translations'] = $record->translations->mapWithKeys(fn ($row) => [$row->locale => collect($row->attributesToArray())->except(['id', 'locale', 'created_at', 'updated_at', 'product_id', 'product_category_id', 'product_size_id', 'finish_id'])->all()])->all();
        if ($record instanceof Product) {
            $data['sizes'] = $record->sizes->map(fn ($size) => ['id' => $size->id, ...self::data($size)])->map(fn ($size) => collect($size)->except('product_id')->all())->all();
            $data['units'] = $record->units->pluck('unit')->all();
            $data['finishes'] = $record->finishes->map(fn ($finish) => ['finish_id' => $finish->id, 'active' => (bool) $finish->pivot->active, 'is_default' => (bool) $finish->pivot->is_default, 'sort_order' => $finish->pivot->sort_order])->all();
            $data['gallery'] = $record->mediaReferences->filter(fn ($ref) => str_starts_with($ref->slot, 'gallery:'))->sortBy(fn ($ref) => (int) substr($ref->slot, 8))->map(fn ($ref) => ['media_asset_id' => $ref->media_asset_id])->values()->all();
        }

        return $data;
    }

    public static function clean(array $data): array
    {
        foreach ($data['translations'] ?? [] as $locale => $copy) {
            if (! array_filter(Arr::flatten($copy), fn ($value) => $value !== null && $value !== '')) {
                unset($data['translations'][$locale]);
            }
        }
        if (isset($data['sizes'])) {
            $data['sizes'] = array_map(self::clean(...), $data['sizes']);
        }
        if (isset($data['gallery'])) {
            $data['gallery'] = array_column($data['gallery'], 'media_asset_id');
        }

        return $data;
    }
}
