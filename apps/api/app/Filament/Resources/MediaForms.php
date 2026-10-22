<?php

namespace App\Filament\Resources;

use App\Catalogue\PublicCatalogue;
use App\Media\MediaLibrary;
use App\Models\MediaAsset;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MediaForms
{
    public static function fields(?string $type = 'image'): array
    {
        $types = $type === 'image' ? ['image/jpeg', 'image/png', 'image/webp'] : ($type === 'video' ? ['video/mp4'] : ['image/jpeg', 'image/png', 'image/webp', 'video/mp4']);
        $file = FileUpload::make('upload')->label(__('admin.file'))->helperText(__($type === 'image' ? 'admin.upload_guidance' : 'admin.video_guidance'))->required()->storeFiles(false)->acceptedFileTypes($types)->maxSize((int) (($type === 'image' ? config('media.max_bytes') : config('video.max_bytes')) / 1024));
        if ($type === 'image') {
            $file->image()->imagePreviewHeight('160');
        }

        return [$file,
            Checkbox::make('client_supplied')->label(__('admin.provenance'))->accepted()->required(),
            Checkbox::make('approve_and_publish')->label(__('admin.approve_publish'))->helperText(__('admin.publication_warning'))->default(false)->visible(fn () => auth()->user()?->isSuperAdmin()),
        ];
    }

    public static function attach(Select $select, ?string $type = 'image', array $profiles = ['thumb', 'card', 'gallery'], bool|Closure $allowPrivate = false): Select
    {
        return $select->createOptionForm(self::fields($type))
            ->createOptionUsing(function (array $data, Select $component) use ($type, $profiles, $allowPrivate) {
                $id = self::upload($data, $type, $profiles);
                $asset = MediaAsset::findOrFail($id);
                $query = MediaAsset::whereKey($id);
                app(PublicCatalogue::class)->eligibleMedia($query);
                if (! $query->exists() && ! $component->evaluate($allowPrivate)) {
                    Notification::make()->warning()->persistent()->title(__('admin.upload_pending'))
                        ->body(__('admin.upload_pending_help', ['name' => $asset->original_filename]))
                        ->actions([Action::make('library')->label(__('admin.open_media_library'))->url(MediaAssetResource::getUrl('index', ['tableSearch' => $asset->original_filename]))->openUrlInNewTab()])->send();

                    // Filament assigns the returned option immediately. Returning null
                    // would clear the previous working cover, so retain its actual state.
                    return $component->getState();
                }
                Notification::make()->success()->title(__($asset->active_revision ? 'admin.upload_published' : 'admin.upload_private'))->send();

                return $id;
            })
            ->createOptionAction(fn (Action $action) => $action->label(__('admin.upload'))->icon('heroicon-o-arrow-up-tray')->button()->modalHeading(__('admin.upload_attach'))->modalSubmitActionLabel(__('admin.upload_attach')))
            ->helperText(__('admin.upload_help'));
    }

    public static function upload(array $data, ?string $type = 'image', array $profiles = ['thumb', 'card', 'gallery']): string
    {
        Gate::authorize('uploadImage', MediaAsset::class);
        $validated = Validator::make($data, ['client_supplied' => 'required|accepted', 'approve_and_publish' => 'sometimes|boolean'])->validate();
        $publish = (bool) ($validated['approve_and_publish'] ?? false);
        if ($publish) {
            Gate::authorize('approve', new MediaAsset);
            Gate::authorize('publish', new MediaAsset);
        }
        $file = $data['upload'] ?? null;
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages(['upload' => __('admin.invalid_upload')]);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $actualType = in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? 'image' : ($mime === 'video/mp4' ? 'video' : null);
        if (! $actualType || ($type !== null && $actualType !== $type)) {
            throw ValidationException::withMessages(['upload' => __('admin.invalid_type')]);
        }
        if ($actualType === 'video') {
            Gate::authorize('uploadVideo', MediaAsset::class);
        }
        $name = mb_strcut(preg_replace('/[^\pL\pN_. -]/u', '-', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 240, 'UTF-8');
        // Derive the suffix from validated content, never dispatch using an untrusted filename.
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'video/mp4' => 'mp4'][$mime];
        $name = pathinfo($name, PATHINFO_FILENAME) ?: 'upload';
        $library = app(MediaLibrary::class);
        try {
            $asset = $library->ingest($file->getRealPath(), 'admin/uploads/'.$name.'.'.$extension, true);
            $revision = $asset->active_revision;
            $existing = $asset->variants()->where('revision', $revision)->pluck('profile')->all();
            if ($asset->processing_state !== 'ready' || $actualType === 'image') {
                $revision = $library->process($asset, array_values(array_unique([...$profiles, ...array_intersect($existing, array_keys(config('media.profiles')))])));
            }
        } catch (ValidationException $error) {
            Notification::make()->danger()->title(__('admin.upload_failed'))->body(__('admin.previous_cover_kept'))->send();
            throw ValidationException::withMessages(['upload' => collect($error->errors())->flatten()->all()]);
        }
        $asset->refresh();
        self::publish($asset, $publish, $revision);

        return $asset->id;
    }

    public static function publish(MediaAsset $asset, bool $requested, ?string $revision = null): void
    {
        if ($requested) {
            Gate::authorize('approve', $asset);
            Gate::authorize('publish', $asset);
            $library = app(MediaLibrary::class);
            $library->approve($asset, auth()->user());
            $revision ??= $asset->variants()->latest('created_at')->firstOrFail()->revision;
            $library->publish($asset, $revision, auth()->user());
        }

    }
}
