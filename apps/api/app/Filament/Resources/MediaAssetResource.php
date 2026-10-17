<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaAssetResource\ManageMediaAssets;
use App\Media\MediaLibrary;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static ?string $navigationLabel = 'Media';

    protected static ?string $modelLabel = 'Media asset';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['variants', 'references', 'imports']);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('preview')->state(fn (MediaAsset $record) => $record->variants_count ? route('media.preview', $record) : null)->size(56),
            TextColumn::make('original_filename')->searchable()->wrap(),
            TextColumn::make('mime'),
            TextColumn::make('width')->label('Dimensions')->formatStateUsing(fn ($state, MediaAsset $record) => $state.' × '.$record->height),
            TextColumn::make('bytes')->numeric(),
            TextColumn::make('client_supplied')->label('Client supplied')->formatStateUsing(fn ($state) => $state ? 'Confirmed' : 'Unconfirmed'),
            TextColumn::make('processing_state')->badge(),
            TextColumn::make('approval_state')->badge(),
            TextColumn::make('active_revision')->label('Publication')->formatStateUsing(fn ($state) => $state ? substr($state, 0, 12) : 'Private')->placeholder('Private'),
            TextColumn::make('references_count')->label('References'),
        ])->recordActions([
            Action::make('inspect')->label('Inspect')->modalContent(fn (MediaAsset $record) => view('admin.media-details', ['record' => $record->load(['variants', 'translations', 'imports', 'references'])]))->modalSubmitAction(false)->modalCancelActionLabel('Close'),
            Action::make('metadata')->label('Edit text')->schema(function (MediaAsset $record) {
                $record->load('translations');
                $fields = [];
                foreach (config('localization.locales') as $locale) {
                    $code = $locale['code'];
                    $copy = $record->translations->firstWhere('locale', $code);
                    $fields[] = TextInput::make("translations.$code.alt")->label($locale['name'].' alt')->maxLength(500)->default($copy?->alt);
                    $fields[] = Textarea::make("translations.$code.caption")->label($locale['name'].' caption')->maxLength(5000)->default($copy?->caption);
                    $fields[] = TextInput::make("translations.$code.credit")->label($locale['name'].' credit')->maxLength(500)->default($copy?->credit);
                }

                return $fields;
            })->action(function (MediaAsset $record, array $data) {
                Gate::authorize('update', $record);
                DB::transaction(function () use ($record, $data) {
                    $record = MediaAsset::whereKey($record->id)->lockForUpdate()->firstOrFail();
                    foreach (array_column(config('localization.locales'), 'code') as $locale) {
                        $record->translations()->updateOrCreate(['locale' => $locale], $data['translations'][$locale] ?? []);
                    }
                });
            }),
            Action::make('provenance')->visible(fn () => auth()->user()->isSuperAdmin())->schema([
                Checkbox::make('confirmed')->label('I confirm the source was supplied by the client (usage approval is separate)')->accepted(),
            ])->action(function (MediaAsset $record) {
                Gate::authorize('approve', $record);
                DB::transaction(fn () => MediaAsset::whereKey($record->id)->lockForUpdate()->firstOrFail()->forceFill(['client_supplied' => true])->save());
            }),
            Action::make('process')->schema([
                Select::make('profiles')->multiple()->required()->options(array_combine(array_keys(config('media.profiles')), array_keys(config('media.profiles'))))->default(['thumb', 'card']),
            ])->action(function (MediaAsset $record, array $data, MediaLibrary $library) {
                Gate::authorize('update', $record);
                $library->process($record, $data['profiles']);
            }),
            Action::make('approve')->visible(fn () => auth()->user()->isSuperAdmin())->requiresConfirmation()->modalDescription('Confirm that the client authorizes public use of this real client-supplied media. File possession alone is insufficient.')->action(fn (MediaAsset $record, MediaLibrary $library) => $library->approve($record, auth()->user())),
            Action::make('publish')->visible(fn () => auth()->user()->isSuperAdmin())->schema([
                Select::make('revision')->required()->options(fn (MediaAsset $record) => $record->variants()->pluck('revision', 'revision')->all()),
            ])->requiresConfirmation()->modalDescription('Publish approved derivatives to immutable public storage. Cached copies cannot be instantly revoked.')->action(fn (MediaAsset $record, array $data, MediaLibrary $library) => $library->publish($record, $data['revision'], auth()->user())),
            Action::make('unpublish')->visible(fn (MediaAsset $record) => auth()->user()->isSuperAdmin() && (bool) $record->active_revision)->requiresConfirmation()->modalDescription('Remove active DTO references. Existing public URLs and cached copies remain available.')->action(fn (MediaAsset $record, MediaLibrary $library) => $library->unpublish($record, auth()->user())),
            Action::make('delete')->color('danger')->requiresConfirmation()->visible(fn (MediaAsset $record) => $record->references_count === 0 && $record->imports_count === 0 && ! $record->active_revision && auth()->user()->isSuperAdmin())->action(fn (MediaAsset $record, MediaLibrary $library) => $library->delete($record, auth()->user())),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageMediaAssets::route('/')];
    }
}
