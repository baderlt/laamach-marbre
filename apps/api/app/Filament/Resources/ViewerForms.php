<?php

namespace App\Filament\Resources;

use App\Media\ModelLibrary;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ViewerForms
{
    public static function upload(array $data): string
    {
        Gate::authorize('uploadModel', MediaAsset::class);
        $file = $data['model_upload'] ?? null;
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages(['model_upload' => 'A real temporary GLB upload is required.']);
        }
        $name = mb_strcut(preg_replace('/[^\pL\pN_. -]/u', '-', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 240, 'UTF-8');

        return app(ModelLibrary::class)->ingest($file->getRealPath(), 'products/models/'.($name ?: 'model.glb'), (bool) ($data['client_supplied'] ?? false))->id;
    }

    public static function uploadFields(): array
    {
        return [FileUpload::make('model_upload')->label('Self-contained GLB')->required()->storeFiles(false)->acceptedFileTypes(['model/gltf-binary', 'application/octet-stream', '.glb'])->maxSize((int) (min(8388608, config('viewer.max_bytes')) / 1024)), Checkbox::make('client_supplied')->label('Client supplied — approval and publication remain separate')->default(false)];
    }

    public static function section(): Section
    {
        $p = 'viewer_3d_config.';
        $fields = [Toggle::make('viewer_3d_enabled')->label('Enable 3D Viewer')->default(false)->helperText('Off by default. Disabling retains saved configuration/assets and removes the public action immediately.'),
            Select::make($p.'mode')->label('Visualization mode')->options(['uploaded_glb' => 'Uploaded GLB — original geometry', 'procedural' => 'Procedural — representative block/slab'])->live(),
            Select::make($p.'model_media_id')->label('Validated model asset')->searchable()->live()->getSearchResultsUsing(fn (string $search) => MediaAsset::where('type', 'model')->where('processing_state', 'ready')->where('original_filename', 'like', '%'.addcslashes($search, '%_\\').'%')->limit(50)->get()->mapWithKeys(fn ($a) => [$a->id => $a->original_filename.' · '.number_format($a->bytes).' bytes · '.$a->approval_state.($a->active_revision ? ' · published' : ' · private')])->all())->getOptionLabelUsing(fn ($value) => MediaAsset::find($value)?->original_filename)->createOptionForm(self::uploadFields())->createOptionUsing(self::upload(...))->helperText('Upload stays private. Review/approve/publish in Media before public activation. Replacements are new immutable assets.'),
            Select::make($p.'model_variant_id')->label('Model revision (empty = current active revision publicly)')->options(fn (Get $get) => MediaVariant::where('media_asset_id', $get($p.'model_media_id'))->where('format', 'glb')->get()->mapWithKeys(fn ($v) => [$v->id => substr($v->revision, 0, 12).' · '.$v->state.' · validator '.($v->metadata['validator_version'] ?? '?')])->all())->helperText('Public use requires the selected revision to be current, approved and published.'),
            CatalogueForms::image($p.'poster_media_id', 'Optional approved poster — product cover otherwise'),
            Select::make($p.'geometry')->label('Procedural geometry')->options(['block' => 'Rectangular block (size depth = height)', 'slab' => 'Slab / panel (size depth = thickness)']),
            Select::make($p.'dimension_source')->label('Procedural dimensions')->options(['fixed' => 'Explicit verified dimensions below', 'product_sizes' => 'Active assigned product sizes'])->live(),
            Checkbox::make($p.'dimensions_verified')->label('I confirm these fixed geometry dimensions are verified, with an explicit unit')->default(false),
        ];
        foreach (['width', 'length', 'depth'] as $axis) {
            $fields[] = TextInput::make($p.$axis)->label('Verified '.$axis)->numeric()->minValue(.0001)->maxValue(1000000);
        }
        $fields[] = Select::make($p.'measurement_unit')->label('Fixed geometry measurement unit')->options(array_combine(config('catalogue.measurement_units'), config('catalogue.measurement_units')));
        $fields[] = Select::make($p.'default_size_id')->label('Default assigned size')->options(fn (Get $get) => collect($get('sizes') ?? [])->filter(fn ($size) => ! empty($size['id']))->mapWithKeys(fn ($size) => [$size['id'] => $size['translations'][config('localization.fallback')]['label'] ?? $size['id']])->all())->helperText('Save new sizes first. Block uses height; slab uses thickness. Width, length and an explicit unit must also be complete.');
        $fields[] = CatalogueForms::image($p.'texture_media_id', 'Optional approved representative texture (procedural only)');
        $fields[] = TextInput::make($p.'roughness')->label('Procedural roughness')->numeric()->minValue(.2)->maxValue(1)->helperText('Default 0.8; non-metallic. Does not map commercial finishes automatically.');
        $fields[] = Select::make($p.'camera_angle')->label('Initial view')->options(['isometric' => 'Isometric', 'front' => 'Front']);
        $fields[] = Select::make($p.'rotation_limit')->label('Orbit range')->options(['full' => 'Full orbit', 'front' => 'Front hemisphere']);
        $fields[] = TextInput::make($p.'zoom_min')->label('Minimum camera distance factor')->numeric()->minValue(.5)->maxValue(1)->helperText('Default 0.65 × framed distance.');
        $fields[] = TextInput::make($p.'zoom_max')->label('Maximum camera distance factor')->numeric()->minValue(1.5)->maxValue(4)->helperText('Default 3.5 × framed distance.');

        return Section::make('3D Visualization')->description('Save first, then use Preview 3D for an authenticated preview of saved validated settings. GLB geometry is never stretched to quotation sizes. Photo gallery remains available.')->schema($fields)->collapsible();
    }
}
