<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaAssetResource\ManageMediaAssets;
use App\Media\MediaLibrary;
use App\Media\VideoLibrary;
use App\Models\Finish;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MediaAssetResource extends Resource
{
    use AdminResourceLabels;

    protected static ?string $model = MediaAsset::class;

    protected static ?string $navigationLabel = 'Media';

    protected static ?string $modelLabel = 'Media asset';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['variants', 'references', 'imports']);
    }

    public static function table(Table $table): Table
    {
        return $table->recordActionsColumnLabel(__('admin.actions'))->emptyStateHeading(__('admin.no_records'))->columns([
            ImageColumn::make('preview')->label(__('admin.preview'))->state(fn (MediaAsset $record) => $record->type === 'image' && $record->variants_count ? route('media.preview', $record) : null)->size(56),
            TextColumn::make('type')->label(__('admin.field_type'))->formatStateUsing(fn ($state) => __('admin.media_type_'.$state))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('original_filename')->label(__('admin.field_original_filename'))->searchable()->wrap()->extraCellAttributes(['style' => 'min-width:15rem']),
            TextColumn::make('mime')->label(__('admin.field_mime'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('width')->label(__('Dimensions'))->formatStateUsing(fn ($state, MediaAsset $record) => $state.' × '.$record->height)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('bytes')->label(__('admin.field_bytes'))->numeric()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('client_supplied')->label(__('Client supplied'))->formatStateUsing(fn ($state) => __($state ? 'admin.confirmed' : 'admin.unconfirmed'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('processing_state')->label(__('admin.field_processing_state'))->formatStateUsing(fn ($state) => __($state === 'pending' ? 'admin.uploaded' : 'admin.'.$state))->badge(),
            TextColumn::make('approval_state')->label(__('admin.field_approval_state'))->formatStateUsing(fn ($state) => __('admin.'.$state))->badge(),
            TextColumn::make('active_revision')->label(__('Publication'))->state(fn (MediaAsset $record) => __($record->active_revision ? 'admin.published' : 'admin.private')),
            TextColumn::make('references_count')->label(__('References')),
        ])->headerActions([
            Action::make('uploadImage')->label(__('admin.upload_image'))->icon('heroicon-o-arrow-up-tray')->schema(MediaForms::fields('image'))->action(function (array $data) {
                $asset = MediaAsset::findOrFail(MediaForms::upload($data));
                Notification::make()->success()->title(__($asset->active_revision ? 'admin.upload_published' : 'admin.upload_private'))->send();
            }),
            Action::make('uploadModel')->label(__('Upload product GLB'))->schema(ViewerForms::uploadFields())->action(function (array $data) {
                $asset = MediaAsset::findOrFail(ViewerForms::upload($data));
                Notification::make()->success()->title(__($asset->active_revision ? 'admin.upload_published' : 'admin.upload_private'))->send();
            }),
            Action::make('uploadVideo')->label(__('Upload project video'))->schema([
                FileUpload::make('video')->label(__('admin.upload_video'))->helperText(__('admin.video_guidance'))->required()->storeFiles(false)->acceptedFileTypes(['video/mp4'])->maxSize((int) (config('video.max_bytes') / 1024)),
                Checkbox::make('client_supplied')->label(__('Client supplied (usage approval is separate)'))->default(false),
            ])->action(function (array $data, MediaLibrary $library) {
                Gate::authorize('uploadVideo', MediaAsset::class);
                $file = $data['video'];
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages(['video' => 'A real temporary upload is required.']);
                }
                $asset = app(VideoLibrary::class)->ingest($file->getRealPath(), 'projects/uploads/'.$file->getClientOriginalName(), (bool) $data['client_supplied']);
                $library->process($asset);
                Notification::make()->success()->title(__('admin.upload_private'))->send();
            }),
        ])->recordActions([ActionGroup::make([
            Action::make('inspect')->label(__('Inspect'))->modalContent(function (MediaAsset $record) {
                $record->load(['variants', 'translations', 'imports', 'references']);
                $owners = [];
                // Resolve each owner type in one query, rather than querying per reference.
                foreach (['products' => Product::class, 'product_viewers' => Product::class, 'product_categories' => ProductCategory::class, 'projects' => Project::class, 'project_categories' => ProjectCategory::class, 'finishes' => Finish::class] as $type => $model) {
                    $keys = $record->references->where('owner_type', $type)->pluck('owner_key')->unique();
                    if ($keys->isNotEmpty()) {
                        foreach ($model::whereKey($keys)->get(['id', 'slug']) as $owner) {
                            $owners[$type][$owner->id] = $owner->slug;
                        }
                    }
                }

                return view('admin.media-details', ['record' => $record, 'owners' => $owners]);
            })->modalSubmitAction(false)->modalCancelActionLabel(__('Close')),
            Action::make('metadata')->label(__('Edit text'))->schema(function (MediaAsset $record) {
                $record->load('translations');
                $fields = [];
                foreach (config('localization.locales') as $locale) {
                    $code = $locale['code'];
                    $copy = $record->translations->firstWhere('locale', $code);
                    $fields[] = TextInput::make("translations.$code.alt")->label($locale['name'].' '.__('admin.field_alt'))->maxLength(500)->default($copy?->alt);
                    $fields[] = Textarea::make("translations.$code.caption")->label($locale['name'].' '.__('admin.field_caption'))->maxLength(5000)->default($copy?->caption);
                    $fields[] = TextInput::make("translations.$code.credit")->label($locale['name'].' '.__('admin.field_credit'))->maxLength(500)->default($copy?->credit);
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
            Action::make('provenance')->label(__('admin.provenance_action'))->visible(fn () => auth()->user()->isSuperAdmin())->schema([
                Checkbox::make('confirmed')->label(__('I confirm the source was supplied by the client (usage approval is separate)'))->accepted(),
            ])->action(function (MediaAsset $record) {
                Gate::authorize('approve', $record);
                DB::transaction(fn () => MediaAsset::whereKey($record->id)->lockForUpdate()->firstOrFail()->forceFill(['client_supplied' => true])->save());
            }),
            Action::make('process')->label(__('admin.process'))->schema([
                Select::make('profiles')->label(__('admin.field_profiles'))->multiple()->required()->options(array_combine(array_keys(config('media.profiles')), array_keys(config('media.profiles'))))->default(['thumb', 'card']),
            ])->action(function (MediaAsset $record, array $data, MediaLibrary $library) {
                Gate::authorize('update', $record);
                AdminActions::save(fn () => $library->process($record, $data['profiles']));
            }),
            Action::make('approve')->label(__('admin.approve'))->visible(fn () => auth()->user()->isSuperAdmin())->requiresConfirmation()->modalDescription(__('Confirm that the client authorizes public use of this real client-supplied media. File possession alone is insufficient.'))->action(fn (MediaAsset $record, MediaLibrary $library) => AdminActions::save(fn () => $library->approve($record, auth()->user()))),
            Action::make('publish')->label(__('admin.publish'))->visible(fn () => auth()->user()->isSuperAdmin())->schema([
                Select::make('revision')->label(__('admin.field_revision'))->required()->options(fn (MediaAsset $record) => $record->variants()->pluck('revision', 'revision')->all()),
            ])->requiresConfirmation()->modalDescription(__('Publish approved derivatives to immutable public storage. Cached copies cannot be instantly revoked.'))->action(fn (MediaAsset $record, array $data, MediaLibrary $library) => AdminActions::save(fn () => $library->publish($record, $data['revision'], auth()->user()))),
            Action::make('unpublish')->label(__('admin.unpublish'))->visible(fn (MediaAsset $record) => auth()->user()->isSuperAdmin() && (bool) $record->active_revision)->requiresConfirmation()->modalDescription(__('Remove active DTO references. Existing public URLs and cached copies remain available.'))->action(fn (MediaAsset $record, MediaLibrary $library) => AdminActions::save(fn () => $library->unpublish($record, auth()->user()))),
            Action::make('delete')->label(__('admin.delete'))->color('danger')->requiresConfirmation()->visible(fn (MediaAsset $record) => $record->references_count === 0 && $record->imports_count === 0 && ! $record->active_revision && auth()->user()->isSuperAdmin())->action(fn (MediaAsset $record, MediaLibrary $library) => AdminActions::save(fn () => $library->delete($record, auth()->user()))),
        ])->label(__('admin.actions'))->button()])->filters([
            SelectFilter::make('type')->label(__('admin.field_type'))->options(collect(['image', 'video', 'model'])->mapWithKeys(fn ($value) => [$value => __('admin.media_type_'.$value)])),
            SelectFilter::make('approval_state')->label(__('admin.field_approval_state'))->options(collect(['pending', 'approved', 'rejected'])->mapWithKeys(fn ($value) => [$value => __('admin.'.$value)])),
            SelectFilter::make('processing_state')->label(__('admin.field_processing_state'))->options(['pending' => __('admin.uploaded'), 'ready' => __('admin.ready'), 'failed' => __('admin.failed')]),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageMediaAssets::route('/')];
    }
}
