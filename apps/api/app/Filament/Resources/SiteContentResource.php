<?php

namespace App\Filament\Resources;

use App\Editorial\SectionForm;
use App\Editorial\SectionLinks;
use App\Editorial\SiteContentWriter;
use App\Models\SiteContent;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SiteContentResource extends Resource
{
    use AdminResourceLabels;

    protected static ?string $model = SiteContent::class;

    protected static ?string $navigationLabel = 'Website content';

    private static function fields(): array
    {
        $fields = [Select::make('key')->live()->options(collect(SiteContent::KEYS)->mapWithKeys(fn ($key) => [$key => __('admin.page_'.$key)])->all())->required()->helperText(__('One record per page; site = global contacts/logo/footer. Existing keys cannot change.')), Toggle::make('published')->default(false)->helperText(__('Super-admin publication only. Product/project featured flags are managed in their existing resources.'))];
        foreach (config('localization.locales') as $locale) {
            $prefix = 'translations.'.$locale['code'].'.';
            foreach (['title', 'eyebrow', 'tagline', 'seo_title', 'cta_title'] as $key) {
                $fields[] = TextInput::make($prefix.$key)->label($locale['name'].' '.$key)->maxLength(160);
            }
            foreach (['body' => 10000, 'address' => 500, 'footer_text' => 1000, 'seo_description' => 320, 'cta_body' => 500] as $key => $max) {
                $fields[] = Textarea::make($prefix.$key)->label($locale['name'].' '.$key)->maxLength($max);
            }
        }
        $options = [];
        $targets = static function (string $locale) use (&$options): array {
            return $options[$locale] ??= SectionLinks::options($locale);
        };
        $tabs = [];
        foreach (config('localization.locales') as $locale) {
            $code = $locale['code'];
            $prefix = 'translations.'.$code.'.';
            $tabs[] = Tab::make($locale['name'])->schema([
                TextInput::make($prefix.'title')->label($locale['name'].' '.__('admin.field_title'))->required($code === config('localization.fallback'))->maxLength(180),
                Textarea::make($prefix.'body')->label($locale['name'].' '.__('admin.field_description'))->maxLength(2000),
                Select::make($prefix.'link')->label($locale['name'].' '.__('admin.field_link'))->options(fn () => $targets($code))->searchable()->helperText(__('Choose a page, a product category or a project category.')),
                Select::make($prefix.'icon')->label($locale['name'].' '.__('admin.field_icon'))->options(array_combine(['stone', 'building', 'ruler', 'craft', 'truck', 'check', 'leaf', 'spark'], collect(['stone', 'building', 'ruler', 'craft', 'truck', 'check', 'leaf', 'spark'])->map(fn ($icon) => __('admin.icon_'.$icon))->all())),
            ]);
        }
        $fields[] = Repeater::make('sections')->label(__('Sections — Français / العربية'))->schema([
            Select::make('group')->label(__('Section placement'))->options(['services' => __('Homepage collection cards'), 'activities' => __('Activities'), 'trust' => __('Trust / benefits'), 'process' => __('Process'), 'story' => __('Story')])->required(),
            CatalogueForms::image('media_asset_id', __('admin.cover'))->live()->helperText(__('admin.section_shared_cover')),
            CatalogueForms::imagePreview('media_asset_id', __('admin.cover')),
            Tabs::make('Section translations')->tabs($tabs),
        ])->itemLabel(fn (array $state): string => ($state['translations'][app()->getLocale()]['title'] ?? '') ?: ($state['translations']['fr']['title'] ?? __('admin.section_new')))->addActionLabel(__('admin.section_add'))->maxItems(16)->defaultItems(0)->collapsible()->helperText(__('Add each section once. Complete Français and العربية in the same card. Drag cards to order. Homepage collection cards display the first six.'));
        foreach (['hero_media_id' => 'Hero', 'secondary_media_id' => 'Secondary image', 'logo_media_id' => 'Logo'] as $key => $label) {
            $fields[] = CatalogueForms::image('data.'.$key, __('admin.'.['hero_media_id' => 'hero', 'secondary_media_id' => 'secondary', 'logo_media_id' => 'logo'][$key]))->live();
            $fields[] = CatalogueForms::imagePreview('data.'.$key, __('admin.'.['hero_media_id' => 'hero', 'secondary_media_id' => 'secondary', 'logo_media_id' => 'logo'][$key]));
        }
        foreach (['phone', 'email', 'map_url'] as $key) {
            $fields[] = TextInput::make('data.'.$key)->maxLength($key === 'map_url' ? 1000 : 254);
        }
        $fields[] = TextInput::make('data.whatsapp.number')->label(__('WhatsApp international number'))->tel()->telRegex('/^\+?[0-9 ()-]{8,40}$/D')->maxLength(40)->helperText(__('Include the country code. No example number is configured automatically.'))->visible(fn (Get $get) => $get('key') === 'site')->disabled(fn () => ! auth()->user()?->isSuperAdmin())->dehydrated(fn () => auth()->user()?->isSuperAdmin());
        foreach (['enabled' => 'Enable WhatsApp requests globally', 'material' => 'Material quotations', 'similar' => 'Similar-project enquiries', 'application' => 'Use this material in my project'] as $key => $label) {
            $fields[] = Toggle::make('data.whatsapp.'.$key)->label(__($label))->default(false)->visible(fn (Get $get) => $get('key') === 'site')->disabled(fn () => ! auth()->user()?->isSuperAdmin())->dehydrated(fn () => auth()->user()?->isSuperAdmin());
        }
        $fields[] = Repeater::make('data.social_links')->schema([TextInput::make('label')->required()->maxLength(40), TextInput::make('url')->url()->required()->maxLength(500)])->maxItems(6)->defaultItems(0)->helperText(__('HTTPS Facebook, Instagram, YouTube or LinkedIn profiles. WhatsApp is configured separately by a super-admin.'));

        return AdminFormLayout::group($fields);
    }

    public static function table(Table $table): Table
    {
        return $table->recordActionsColumnLabel(__('admin.actions'))->emptyStateHeading(__('admin.no_records'))->columns([TextColumn::make('key')->label(__('admin.field_key'))->formatStateUsing(fn ($state) => __('admin.page_'.$state))->searchable(), TextColumn::make('published')->label(__('admin.publication'))->formatStateUsing(fn ($state) => __($state ? 'admin.published' : 'admin.draft'))->badge(), TextColumn::make('updated_at')->label(__('admin.field_updated_at'))->dateTime()])->headerActions([Action::make('create')->label(__('admin.create'))->modalSubmitActionLabel(__('admin.save'))->modalWidth('7xl')->schema(self::fields())->action(fn (array $data, SiteContentWriter $writer) => AdminActions::save(fn () => $writer->save(null, SectionForm::input($data), auth()->user())))])->recordActions([Action::make('edit')->label(__('admin.edit'))->modalSubmitActionLabel(__('admin.save'))->modalWidth('7xl')->fillForm(fn (SiteContent $record) => SectionForm::fill(collect($record->attributesToArray())->except(['created_at', 'updated_at'])->all()))->schema(self::fields())->action(fn (SiteContent $record, array $data, SiteContentWriter $writer) => AdminActions::save(fn () => $writer->save($record, SectionForm::input($data), auth()->user())))]);
    }

    public static function getPages(): array
    {
        return ['index' => SiteContentPages\ManageSiteContent::route('/')];
    }
}
