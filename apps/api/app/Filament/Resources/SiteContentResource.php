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
    protected static ?string $model = SiteContent::class;

    protected static ?string $navigationLabel = 'Website content';

    private static function fields(): array
    {
        $fields = [Select::make('key')->live()->options(array_combine(SiteContent::KEYS, SiteContent::KEYS))->required()->helperText('One record per page; site = global contacts/logo/footer. Existing keys cannot change.'), Toggle::make('published')->default(false)->helperText('Super-admin publication only. Product/project featured flags are managed in their existing resources.')];
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
                TextInput::make($prefix.'title')->label($locale['name'].' title')->required($code === config('localization.fallback'))->maxLength(180),
                Textarea::make($prefix.'body')->label($locale['name'].' description')->maxLength(2000),
                CatalogueForms::image($prefix.'media_asset_id', $locale['name'].' image')->live(),
                CatalogueForms::imagePreview($prefix.'media_asset_id', $locale['name'].' image'),
                Select::make($prefix.'link')->label($locale['name'].' destination')->options(fn () => $targets($code))->searchable()->helperText('Choose a page, a product category or a project category.'),
                Select::make($prefix.'icon')->label($locale['name'].' icon')->options(array_combine(['stone', 'building', 'ruler', 'craft', 'truck', 'check', 'leaf', 'spark'], ['stone', 'building', 'ruler', 'craft', 'truck', 'check', 'leaf', 'spark'])),
            ]);
        }
        $fields[] = Repeater::make('sections')->label('Sections — Français / العربية')->schema([
            Select::make('group')->label('Section placement')->options(['services' => 'Homepage collection cards', 'activities' => 'Activities', 'trust' => 'Trust / benefits', 'process' => 'Process', 'story' => 'Story'])->required(),
            Tabs::make('Section translations')->tabs($tabs),
        ])->itemLabel(fn (array $state): string => $state['translations'][config('localization.fallback')]['title'] ?? $state['translations']['ar']['title'] ?? 'New section')->addActionLabel('Add a bilingual section')->maxItems(16)->defaultItems(0)->collapsible()->helperText('Add each section once. Complete Français and العربية in the same card. Drag cards to order. Homepage collection cards display the first six.');
        foreach (['hero_media_id' => 'Hero', 'secondary_media_id' => 'Secondary image', 'logo_media_id' => 'Logo'] as $key => $label) {
            $fields[] = CatalogueForms::image('data.'.$key, $label.' — approved published image')->live();
            $fields[] = CatalogueForms::imagePreview('data.'.$key, $label.' image');
        }
        foreach (['phone', 'email', 'map_url'] as $key) {
            $fields[] = TextInput::make('data.'.$key)->maxLength($key === 'map_url' ? 1000 : 254);
        }
        $fields[] = TextInput::make('data.whatsapp.number')->label('WhatsApp international number')->tel()->telRegex('/^\+?[0-9 ()-]{8,40}$/D')->maxLength(40)->helperText('Include the country code. No example number is configured automatically.')->visible(fn (Get $get) => $get('key') === 'site')->disabled(fn () => ! auth()->user()?->isSuperAdmin())->dehydrated(fn () => auth()->user()?->isSuperAdmin());
        foreach (['enabled' => 'Enable WhatsApp requests globally', 'material' => 'Material quotations', 'similar' => 'Similar-project enquiries', 'application' => 'Use this material in my project'] as $key => $label) {
            $fields[] = Toggle::make('data.whatsapp.'.$key)->label($label)->default(false)->visible(fn (Get $get) => $get('key') === 'site')->disabled(fn () => ! auth()->user()?->isSuperAdmin())->dehydrated(fn () => auth()->user()?->isSuperAdmin());
        }
        $fields[] = Repeater::make('data.social_links')->schema([TextInput::make('label')->required()->maxLength(40), TextInput::make('url')->url()->required()->maxLength(500)])->maxItems(6)->defaultItems(0)->helperText('HTTPS Facebook, Instagram, YouTube or LinkedIn profiles. WhatsApp is configured separately by a super-admin.');

        return $fields;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('key'), TextColumn::make('published')->badge(), TextColumn::make('updated_at')->dateTime()])->headerActions([Action::make('create')->schema(self::fields())->action(fn (array $data, SiteContentWriter $writer) => $writer->save(null, SectionForm::input($data), auth()->user()))])->recordActions([Action::make('edit')->fillForm(fn (SiteContent $record) => SectionForm::fill(collect($record->attributesToArray())->except(['created_at', 'updated_at'])->all()))->schema(self::fields())->action(fn (SiteContent $record, array $data, SiteContentWriter $writer) => $writer->save($record, SectionForm::input($data), auth()->user()))]);
    }

    public static function getPages(): array
    {
        return ['index' => SiteContentPages\ManageSiteContent::route('/')];
    }
}
