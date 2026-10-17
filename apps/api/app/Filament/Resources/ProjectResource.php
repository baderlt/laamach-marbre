<?php

namespace App\Filament\Resources;

use App\Models\CatalogueRecord;
use App\Models\Project;
use App\Projects\ProjectWriter;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static bool $category = false;

    public static function table(Table $table): Table
    {
        $category = static::$category;

        return $table->columns([TextColumn::make('slug')->searchable(), TextColumn::make($category ? 'active' : 'status')->badge(), TextColumn::make('sort_order')->sortable(), ...($category ? [] : [TextColumn::make('reference')])])->headerActions([
            Action::make('create')->schema(ProjectForms::fields($category))->action(fn (array $data, ProjectWriter $writer) => $writer->save(null, ProjectForms::clean($data), auth()->user(), $category)),
        ])->recordActions([
            Action::make('edit')->fillForm(fn (CatalogueRecord $record) => ProjectForms::data($record))->schema(ProjectForms::fields($category))->action(fn (CatalogueRecord $record, array $data, ProjectWriter $writer) => $writer->save($record, ProjectForms::clean($data), auth()->user(), $category)),
            Action::make('delete')->color('danger')->visible(fn () => auth()->user()->isSuperAdmin())->requiresConfirmation()->action(fn (CatalogueRecord $record, ProjectWriter $writer) => $writer->delete($record, auth()->user())),
        ])->defaultSort('sort_order')->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => CataloguePages\ManageProjects::route('/')];
    }
}
