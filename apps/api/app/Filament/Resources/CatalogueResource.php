<?php

namespace App\Filament\Resources;

use App\Catalogue\CatalogueWriter;
use App\Models\CatalogueRecord;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

abstract class CatalogueResource extends Resource
{
    protected static string $kind;

    protected static string $manager;

    public static function table(Table $table): Table
    {
        $kind = static::$kind;

        return $table->columns([TextColumn::make('slug')->searchable(), TextColumn::make($kind === 'product' ? 'status' : 'active')->badge(), TextColumn::make('sort_order')->sortable(), ...($kind === 'product' ? [TextColumn::make('reference')] : [])])->headerActions([
            Action::make('create')->schema(CatalogueForms::fields($kind))->action(function (array $data, CatalogueWriter $writer) use ($kind) {
                $writer->$kind(null, CatalogueForms::clean($data), auth()->user());
            }),
        ])->recordActions([
            Action::make('edit')->fillForm(fn (CatalogueRecord $record) => CatalogueForms::data($record))->schema(CatalogueForms::fields($kind))->action(function (CatalogueRecord $record, array $data, CatalogueWriter $writer) use ($kind) {
                $writer->$kind($record, CatalogueForms::clean($data), auth()->user());
            }),
            Action::make('delete')->color('danger')->visible(fn () => auth()->user()->isSuperAdmin())->requiresConfirmation()->action(fn (CatalogueRecord $record, CatalogueWriter $writer) => $writer->delete($record, auth()->user())),
        ])->defaultSort('sort_order')->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => static::$manager::route('/')];
    }
}
