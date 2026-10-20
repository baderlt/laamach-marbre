<?php

namespace App\Filament\Resources;

use App\Catalogue\CatalogueWriter;
use App\Models\CatalogueRecord;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

abstract class CatalogueResource extends Resource
{
    use AdminResourceLabels;

    protected static string $kind;

    protected static string $manager;

    public static function table(Table $table): Table
    {
        $kind = static::$kind;

        return $table->recordActionsColumnLabel(__('admin.actions'))->emptyStateHeading(__('admin.no_records'))->filters([$kind === 'product' ? SelectFilter::make('status')->label(__('admin.field_status'))->options(['draft' => __('admin.draft'), 'published' => __('admin.published')]) : TernaryFilter::make('active')->label(__('admin.field_active'))])->columns([TextColumn::make('slug')->label(__('admin.field_slug'))->searchable(), TextColumn::make($kind === 'product' ? 'status' : 'active')->label(__('admin.field_status'))->formatStateUsing(fn ($state) => $kind === 'product' ? __('admin.'.$state) : __($state ? 'admin.active' : 'admin.inactive'))->badge(), TextColumn::make('sort_order')->label(__('admin.field_sort_order'))->sortable(), ...($kind === 'product' ? [TextColumn::make('reference')->label(__('admin.field_reference'))] : [])])->headerActions([
            Action::make('create')->label(__('admin.create'))->modalSubmitActionLabel(__('admin.save'))->modalWidth('7xl')->schema(CatalogueForms::fields($kind))->action(function (array $data, CatalogueWriter $writer) use ($kind) {
                AdminActions::save(fn () => $writer->$kind(null, CatalogueForms::clean($data), auth()->user()));
            }),
        ])->recordActions([
            Action::make('edit')->label(__('admin.edit'))->modalSubmitActionLabel(__('admin.save'))->modalWidth('7xl')->fillForm(fn (CatalogueRecord $record) => CatalogueForms::data($record))->schema(CatalogueForms::fields($kind))->action(function (CatalogueRecord $record, array $data, CatalogueWriter $writer) use ($kind) {
                AdminActions::save(fn () => $writer->$kind($record, CatalogueForms::clean($data), auth()->user()));
            }),
            ...($kind === 'product' ? [Action::make('preview3d')->label(__('admin.preview_3d'))->url(fn (CatalogueRecord $record) => route('viewer.preview', $record))->openUrlInNewTab()->visible(fn (CatalogueRecord $record) => in_array($record->viewer_3d_config['mode'] ?? null, ['uploaded_glb', 'procedural'], true))] : []),
            Action::make('delete')->label(__('admin.delete'))->color('danger')->visible(fn () => auth()->user()->isSuperAdmin())->requiresConfirmation()->action(fn (CatalogueRecord $record, CatalogueWriter $writer) => AdminActions::save(fn () => $writer->delete($record, auth()->user()))),
        ])->defaultSort('sort_order')->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => static::$manager::route('/')];
    }
}
