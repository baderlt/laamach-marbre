<?php

namespace App\Filament\Resources;

use App\Models\CatalogueRecord;
use App\Models\Project;
use App\Projects\ProjectWriter;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    use AdminResourceLabels;

    protected static ?string $model = Project::class;

    protected static bool $category = false;

    public static function table(Table $table): Table
    {
        $category = static::$category;

        return $table->recordActionsColumnLabel(__('admin.actions'))->emptyStateHeading(__('admin.no_records'))->filters([$category ? TernaryFilter::make('active')->label(__('admin.field_active')) : SelectFilter::make('status')->label(__('admin.field_status'))->options(['draft' => __('admin.draft'), 'published' => __('admin.published')])])->columns([TextColumn::make('slug')->label(__('admin.field_slug'))->searchable(), TextColumn::make($category ? 'active' : 'status')->label(__('admin.field_status'))->formatStateUsing(fn ($state) => $category ? __($state ? 'admin.active' : 'admin.inactive') : __('admin.'.$state))->badge(), TextColumn::make('sort_order')->label(__('admin.field_sort_order'))->sortable(), ...($category ? [] : [TextColumn::make('reference')->label(__('admin.field_reference'))])])->headerActions([
            Action::make('create')->label(__('admin.create'))->modalSubmitActionLabel(__('admin.save'))->modalWidth('7xl')->schema(ProjectForms::fields($category))->action(fn (array $data, ProjectWriter $writer) => AdminActions::save(fn () => $writer->save(null, ProjectForms::clean($data), auth()->user(), $category))),
        ])->recordActions([
            Action::make('edit')->label(__('admin.edit'))->modalSubmitActionLabel(__('admin.save'))->modalWidth('7xl')->fillForm(fn (CatalogueRecord $record) => ProjectForms::data($record))->schema(ProjectForms::fields($category))->action(fn (CatalogueRecord $record, array $data, ProjectWriter $writer) => AdminActions::save(fn () => $writer->save($record, ProjectForms::clean($data), auth()->user(), $category))),
            Action::make('delete')->label(__('admin.delete'))->color('danger')->visible(fn () => auth()->user()->isSuperAdmin())->requiresConfirmation()->action(fn (CatalogueRecord $record, ProjectWriter $writer) => AdminActions::save(fn () => $writer->delete($record, auth()->user()))),
        ])->defaultSort('sort_order')->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => CataloguePages\ManageProjects::route('/')];
    }
}
