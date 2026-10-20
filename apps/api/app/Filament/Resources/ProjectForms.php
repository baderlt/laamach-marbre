<?php

namespace App\Filament\Resources;

use App\Models\CatalogueRecord;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Projects\PublicProjects;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;

class ProjectForms
{
    public static function fields(bool $category): array
    {
        $fields = [TextInput::make('slug')->required()->maxLength(120)->helperText(__('Explicit URL slug. Locked after first publication.')), TextInput::make('sort_order')->integer()->default(0)->required(), self::media('cover_media_id', 'Approved cover photo', 'image')];
        $fields[] = CatalogueForms::imagePreview('cover_media_id', __('admin.cover'));
        if ($category) {
            $fields[] = Toggle::make('active')->default(false);
        } else {
            $fields = [...$fields, Select::make('status')->options(['draft' => __('Draft'), 'published' => __('Published (super-admin)')])->default('draft')->required(), Toggle::make('featured')->default(false), Select::make('categories')->multiple()->searchable()->options(fn () => ProjectCategory::orderBy('slug')->get()->mapWithKeys(fn ($item) => [$item->id => $item->slug.($item->active ? '' : ' (inactive; existing assignment only)')]))->maxItems(config('projects.max_categories')), TextInput::make('location')->maxLength(200), TextInput::make('year')->integer()->minValue(1900)->maxValue(2100), TextInput::make('area')->numeric()->minValue(0.01), Select::make('area_unit')->options(array_combine(config('projects.area_units'), config('projects.area_units')))];
            $fields[] = Repeater::make('products')->label(__('Associated catalogue products'))->schema([Select::make('product_id')->options(fn () => Product::orderBy('slug')->pluck('slug', 'id'))->searchable()->required(), TextInput::make('sort_order')->integer()->default(0)->required()])->maxItems(config('projects.max_products'))->defaultItems(0)->reorderable(false)->helperText(__('Editorial product suggestions for this project. These links do not claim installation in its photos. Public display uses sort_order.'));
            $captions = [];
            foreach (config('localization.locales') as $locale) {
                $captions[] = Textarea::make('captions.'.$locale['code'])->label($locale['name'].' '.__('admin.field_caption'))->maxLength(500);
            }
            $fields[] = Repeater::make('gallery')->schema([Select::make('type')->label(__('Photo or video'))->options(['image' => __('Photo'), 'video' => __('Video (MP4)')])->default('image')->required()->live(), self::media('media_asset_id', 'Approved published gallery media')->required(), CatalogueForms::imagePreview('media_asset_id', __('admin.cover'))->visible(fn (Get $get) => $get('type') === 'image'), self::media('poster_media_id', 'Optional approved video poster', 'image')->visible(fn (Get $get) => $get('type') === 'video'), CatalogueForms::imagePreview('poster_media_id', __('admin.field_poster_media_id'))->visible(fn (Get $get) => $get('type') === 'video'), ...$captions])->maxItems(config('projects.max_gallery'))->defaultItems(0)->helperText(__('admin.gallery_help'));
        }
        foreach (config('localization.locales') as $locale) {
            $prefix = 'translations.'.$locale['code'].'.';
            $fields[] = TextInput::make($prefix.($category ? 'name' : 'title'))->label($locale['name'].' '.($category ? 'name' : 'title'))->required($locale['code'] === config('localization.fallback'))->maxLength(160);
            $fields[] = Textarea::make($prefix.'description')->label($locale['name'].' '.__('admin.field_description'))->maxLength(10000);
            if (! $category) {
                $fields[] = Textarea::make($prefix.'summary')->label($locale['name'].' '.__('admin.field_summary'))->maxLength(500);
                $fields[] = TextInput::make($prefix.'area_display')->label($locale['name'].' area / dimensions display')->maxLength(200);
            }
            $fields[] = TextInput::make($prefix.'seo_title')->label($locale['name'].' SEO title')->maxLength(160);
            $fields[] = Textarea::make($prefix.'seo_description')->label($locale['name'].' SEO description')->maxLength(320);
        }

        return AdminFormLayout::group($fields);
    }

    private static function media(string $key, string $label, ?string $type = null): Select
    {
        return MediaForms::attach(Select::make($key)->label(__($label))->live()->searchable()->getSearchResultsUsing(fn (string $search) => app(PublicProjects::class)->assetQuery($type)->where('original_filename', 'like', '%'.addcslashes($search, '%_\\').'%')->limit(50)->get()->mapWithKeys(fn ($asset) => [$asset->id => __('admin.media_type_'.$asset->type).': '.$asset->original_filename.' · '.substr($asset->id, 0, 8)])->all())->getOptionLabelUsing(fn ($value) => MediaAsset::find($value)?->original_filename), $type);
    }

    public static function data(CatalogueRecord $record): array
    {
        $data = Arr::except($record->getAttributes(), ['id', 'reference', 'created_at', 'updated_at', 'published_at']);
        $data['translations'] = $record->translations->mapWithKeys(fn ($row) => [$row->locale => Arr::except($row->attributesToArray(), ['id', 'locale', 'project_id', 'project_category_id', 'created_at', 'updated_at'])])->all();
        if ($record instanceof Project) {
            $data['categories'] = $record->categories->pluck('id')->all();
            $data['products'] = $record->products->map(fn ($product) => ['product_id' => $product->id, 'sort_order' => $product->pivot->sort_order])->all();
            $data['gallery'] = $record->gallery->map(fn ($item) => Arr::only($item->attributesToArray(), ['type', 'media_asset_id', 'poster_media_id', 'captions']))->all();
        }

        return $data;
    }

    public static function clean(array $data): array
    {
        foreach ($data['translations'] ?? [] as $locale => $copy) {
            if (! array_filter($copy, fn ($value) => $value !== null && $value !== '')) {
                unset($data['translations'][$locale]);
            }
        }

        return $data;
    }
}
