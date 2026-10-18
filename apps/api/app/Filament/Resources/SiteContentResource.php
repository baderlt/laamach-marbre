<?php

namespace App\Filament\Resources;

use App\Editorial\SiteContentWriter;
use App\Models\SiteContent;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
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
            $fields[] = Repeater::make($prefix.'sections')->label($locale['name'].' sections')->schema([Select::make('group')->options(array_combine(['activities', 'services', 'trust', 'process', 'story'], ['activities', 'services', 'trust', 'process', 'story']))->required(), TextInput::make('title')->required()->maxLength(180), Textarea::make('body')->maxLength(2000), Select::make('icon')->options(array_combine(['stone', 'building', 'ruler', 'craft', 'truck', 'check', 'leaf', 'spark'], ['stone', 'building', 'ruler', 'craft', 'truck', 'check', 'leaf', 'spark'])), CatalogueForms::image('media_asset_id', 'Approved section image'), Select::make('link')->options(array_combine(['products', 'projects', 'services', 'about', 'contact'], ['products', 'projects', 'services', 'about', 'contact']))])->maxItems(16)->defaultItems(0)->helperText('Drag to order. Enter verified company facts only.');
        }
        foreach (['hero_media_id' => 'Hero', 'secondary_media_id' => 'Secondary image', 'logo_media_id' => 'Logo'] as $key => $label) {
            $fields[] = CatalogueForms::image('data.'.$key, $label.' — approved published image');
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
        return $table->columns([TextColumn::make('key'), TextColumn::make('published')->badge(), TextColumn::make('updated_at')->dateTime()])->headerActions([Action::make('create')->schema(self::fields())->action(fn (array $data, SiteContentWriter $writer) => $writer->save(null, $data, auth()->user()))])->recordActions([Action::make('edit')->fillForm(fn (SiteContent $record) => collect($record->attributesToArray())->except(['created_at', 'updated_at'])->all())->schema(self::fields())->action(fn (SiteContent $record, array $data, SiteContentWriter $writer) => $writer->save($record, $data, auth()->user()))]);
    }

    public static function getPages(): array
    {
        return ['index' => SiteContentPages\ManageSiteContent::route('/')];
    }
}
