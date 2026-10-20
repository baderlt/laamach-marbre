<?php

namespace App\Filament\Pages;

use App\Editorial\SiteContentWriter;
use App\Filament\Resources\AdminActions;
use App\Filament\Resources\CatalogueForms;
use App\Models\SiteContent;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class ContactSettings extends Page
{
    protected string $view = 'admin.contact-settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('manageSettings', SiteContent::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.contact_settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.group_settings');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function getSubheading(): ?string
    {
        return __('admin.contact_help');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $record = SiteContent::find('site');
        $this->form->fill(['published' => $record?->published ?? false, 'data' => $record?->data ?? [], 'translations' => $record?->translations ?? []]);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (config('localization.locales') as $locale) {
            $p = 'translations.'.$locale['code'].'.';
            $tabs[] = Tab::make($locale['name'])->extraAttributes(['dir' => $locale['direction']])->schema([
                Textarea::make($p.'address')->label(__('admin.field_address'))->maxLength(500),
                TextInput::make($p.'tagline')->label(__('admin.field_tagline'))->maxLength(200),
                Textarea::make($p.'footer_text')->label(__('admin.field_footer_text'))->maxLength(1000),
            ]);
        }

        return $schema->statePath('data')->components([
            Section::make(__('admin.publication'))->schema([Toggle::make('published')->label(__('admin.published'))]),
            Section::make(__('admin.company'))->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('data.phone')->label(__('admin.field_phone'))->tel()->maxLength(40)->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('data.email')->label(__('admin.field_email'))->email()->maxLength(254)->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('data.map_url')->label(__('admin.field_map_url'))->url()->maxLength(1000)->extraInputAttributes(['dir' => 'ltr']),
            ]),
            Tabs::make(__('admin.translations'))->tabs($tabs),
            Section::make(__('admin.primary_whatsapp'))->description(__('admin.primary_help'))->schema([
                TextInput::make('data.whatsapp.number')->label(__('admin.field_number'))->tel()->maxLength(40)->extraInputAttributes(['dir' => 'ltr']),
                Toggle::make('data.whatsapp.enabled')->label(__('admin.whatsapp_enabled')),
                Toggle::make('data.whatsapp.material')->label(__('admin.whatsapp_material')),
                Toggle::make('data.whatsapp.similar')->label(__('admin.whatsapp_similar')),
                Toggle::make('data.whatsapp.application')->label(__('admin.whatsapp_application')),
            ]),
            Section::make(__('admin.additional_contacts'))->description(__('admin.additional_help'))->schema([
                Repeater::make('data.additional_contacts')->label(__('admin.additional_contacts'))->defaultItems(0)->maxItems(6)->reorderableWithButtons()->schema([
                    TextInput::make('label')->label(__('admin.field_label'))->required()->maxLength(40),
                    TextInput::make('number')->label(__('admin.field_number'))->required()->tel()->maxLength(40)->extraInputAttributes(['dir' => 'ltr']),
                ]),
            ]),
            Section::make(__('admin.social'))->schema([
                Repeater::make('data.social_links')->label(__('admin.social'))->defaultItems(0)->maxItems(6)->reorderableWithButtons()->schema([
                    TextInput::make('label')->label(__('admin.field_label'))->required()->maxLength(40),
                    TextInput::make('url')->label(__('admin.field_url'))->required()->url()->maxLength(500)->extraInputAttributes(['dir' => 'ltr']),
                ]),
            ]),
            Section::make(__('admin.logo'))->schema([CatalogueForms::image('data.logo_media_id', __('admin.logo'))->live(), CatalogueForms::imagePreview('data.logo_media_id', __('admin.logo'))]),
        ]);
    }

    public function save(): void
    {
        Gate::authorize('manageSettings', SiteContent::class);
        $state = $this->form->getState();
        $record = SiteContent::find('site');
        AdminActions::save(fn () => app(SiteContentWriter::class)->save($record, [
            'key' => 'site', 'published' => $state['published'],
            'data' => array_replace($record?->data ?? [], $state['data'] ?? []),
            'translations' => array_replace_recursive($record?->translations ?? [], $state['translations'] ?? []),
        ], auth()->user()));
    }
}
