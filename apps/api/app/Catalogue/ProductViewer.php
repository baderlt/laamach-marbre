<?php

namespace App\Catalogue;

use App\Media\PublishedMedia;
use App\Models\MediaAsset;
use App\Models\MediaReference;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductViewer
{
    public function validate(?array $input): ?array
    {
        if ($input === null) {
            return null;
        }

        return Validator::make(['viewer' => $input], [
            'viewer' => 'array:mode,model_media_id,model_variant_id,poster_media_id,texture_media_id,geometry,dimension_source,dimensions_verified,width,length,depth,measurement_unit,default_size_id,roughness,camera_angle,rotation_limit,zoom_min,zoom_max',
            'viewer.mode' => ['nullable', Rule::in(['uploaded_glb', 'procedural'])],
            'viewer.geometry' => ['nullable', Rule::in(['block', 'slab'])],
            'viewer.dimension_source' => ['nullable', Rule::in(['fixed', 'product_sizes'])],
            'viewer.dimensions_verified' => 'sometimes|boolean',
            'viewer.width' => 'nullable|numeric|between:0.0001,1000000', 'viewer.length' => 'nullable|numeric|between:0.0001,1000000', 'viewer.depth' => 'nullable|numeric|between:0.0001,1000000',
            'viewer.measurement_unit' => ['nullable', Rule::in(config('catalogue.measurement_units'))],
            'viewer.default_size_id' => 'nullable|uuid',
            'viewer.model_media_id' => 'nullable|uuid', 'viewer.model_variant_id' => 'nullable|uuid', 'viewer.poster_media_id' => 'nullable|uuid', 'viewer.texture_media_id' => 'nullable|uuid',
            'viewer.roughness' => 'nullable|numeric|between:0.2,1',
            'viewer.camera_angle' => ['nullable', Rule::in(['isometric', 'front'])],
            'viewer.rotation_limit' => ['nullable', Rule::in(['full', 'front'])],
            'viewer.zoom_min' => 'nullable|numeric|between:0.5,1', 'viewer.zoom_max' => 'nullable|numeric|between:1.5,4',
        ])->validate()['viewer'];
    }

    public function save(Product $product, array $input): void
    {
        $config = $this->validate(array_key_exists('viewer_3d_config', $input) ? $input['viewer_3d_config'] : $product->viewer_3d_config);
        $enabled = (bool) ($input['viewer_3d_enabled'] ?? $product->viewer_3d_enabled);
        $roles = [];
        foreach (['model', 'poster', 'texture'] as $slot) {
            if ($id = $config[$slot.'_media_id'] ?? null) {
                $roles[$slot] = $id;
            }
        }
        $assets = MediaAsset::whereIn('id', array_values($roles))->with(['variants', 'translations'])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($roles as $slot => $id) {
            if (($assets->get($id)?->type) !== ($slot === 'model' ? 'model' : 'image')) {
                $this->fail('Assign an existing M2 asset of the correct type.');
            }
        }
        if ($variantId = $config['model_variant_id'] ?? null) {
            if (! ($assets->get($config['model_media_id'] ?? '')?->variants->contains('id', $variantId))) {
                $this->fail('Model revision does not belong to the selected model.');
            }
        }
        $product->forceFill(['viewer_3d_enabled' => $enabled, 'viewer_3d_config' => $config]);
        if ($enabled) {
            $this->configuration($product, $assets, $product->status !== 'published', config('localization.fallback'));
        }
        $product->save();
        $product->viewerReferences()->delete();
        foreach ($roles as $slot => $id) {
            $ref = new MediaReference;
            $ref->forceFill(['owner_type' => 'product_viewers', 'owner_key' => $product->id, 'slot' => $slot, 'media_asset_id' => $id, 'media_variant_id' => $slot === 'model' ? ($config['model_variant_id'] ?? null) : null])->save();
        }
    }

    public function public(Product $product, string $locale): ?array
    {
        if (! $product->viewer_3d_enabled || $product->status !== 'published' || ! $product->published_at || $product->published_at->isFuture()) {
            return null;
        }
        try {
            $product->loadMissing(['viewerReferences.asset.variants', 'viewerReferences.asset.translations']);

            return $this->configuration($product, $product->viewerReferences->pluck('asset')->keyBy('id'), false, $locale);
        } catch (ValidationException) {
            return null;
        }
    }

    public function preview(Product $product, string $locale): array
    {
        $product->load(['viewerReferences.asset.variants', 'viewerReferences.asset.translations', 'sizes.translations']);

        return $this->configuration($product, $product->viewerReferences->pluck('asset')->keyBy('id'), true, $locale);
    }

    private function configuration(Product $product, $assets, bool $private, string $locale): array
    {
        $c = $this->validate($product->viewer_3d_config);
        if (! $c || ! in_array($c['mode'] ?? null, ['uploaded_glb', 'procedural'], true)) {
            $this->fail('Choose a supported 3D mode.');
        }
        $catalogue = app(PublicCatalogue::class);
        $images = [];
        foreach (['poster', ...($c['mode'] === 'procedural' ? ['texture'] : [])] as $role) {
            $image = $assets->get($c[$role.'_media_id'] ?? '');
            if (empty($c[$role.'_media_id'])) {
                $images[$role] = null;

                continue;
            }
            if (! $image || $image->type !== 'image' || $image->processing_state !== 'ready') {
                $this->fail('The assigned image is unavailable.');
            }
            if (! $private && ! $this->eligible($image, 'image')) {
                $this->fail('Viewer images must be approved and published.');
            }
            if ($private && ! $this->eligible($image, 'image')) {
                $variant = $image->variants->where('format', 'webp')->sortBy('width')->first();
                if (! $variant) {
                    $this->fail('Process the image before preview.');
                }
                $images[$role] = ['id' => $image->id, 'alt' => '', 'variants' => [['url' => route('media.preview', $image), 'hash' => $variant->checksum, 'width' => $variant->width, 'height' => $variant->height, 'format' => 'webp', 'profile' => $variant->profile, 'revision' => $variant->revision, 'bytes' => $variant->bytes]]];
            } else {
                $images[$role] = $catalogue->image($image, $locale);
            }
        }
        $result = ['mode' => $c['mode'], 'poster' => $images['poster'], 'camera' => ['angle' => $c['camera_angle'] ?? 'isometric', 'rotation' => $c['rotation_limit'] ?? 'full', 'min_distance_factor' => (float) ($c['zoom_min'] ?? .65), 'max_distance_factor' => (float) ($c['zoom_max'] ?? 3.5)], 'model' => null, 'geometry' => null, 'dimensions_m' => null, 'sizes' => [], 'default_size_id' => null, 'material' => null];
        if ($c['mode'] === 'uploaded_glb') {
            $model = $assets->get($c['model_media_id'] ?? '');
            if (! $model || $model->type !== 'model' || $model->processing_state !== 'ready') {
                $this->fail('Select a validated GLB model.');
            }
            $variant = ! empty($c['model_variant_id']) ? $model->variants->firstWhere('id', $c['model_variant_id']) : ($private ? $model->variants->sortByDesc('created_at')->first() : $model->variants->firstWhere('publication_revision', $model->active_revision));
            if (! $variant || $variant->profile !== 'model' || $variant->format !== 'glb' || ($variant->metadata['validator_version'] ?? null) !== config('viewer.validator_version') || ($variant->metadata['sha256'] ?? '') !== $variant->checksum || $variant->bytes > min(8388608, config('viewer.max_bytes'))) {
                $this->fail('Choose a current validated model revision.');
            }
            if (! $private && (! $this->eligible($model, 'model') || $variant->state !== 'published' || $variant->publication_revision !== $model->active_revision)) {
                $this->fail('The model revision must be approved, published and active.');
            }
            $url = $private ? route('viewer.model', $variant) : app(PublishedMedia::class)->variant($variant)['url'];
            $result['model'] = ['url' => $url, 'hash' => $variant->checksum, 'bytes' => $variant->bytes, 'revision' => $variant->revision];

            return $result;
        }
        if (! in_array($c['geometry'] ?? null, ['block', 'slab'], true)) {
            $this->fail('Choose a block or slab geometry.');
        }
        $result['geometry'] = $c['geometry'];
        if (($c['dimension_source'] ?? null) === 'product_sizes') {
            foreach ($product->sizes as $size) {
                if (! $size->active) {
                    continue;
                }
                try {
                    $dimensions = $this->dimensions($size->width, $size->length, $c['geometry'] === 'block' ? $size->height : $size->thickness, $size->measurement_unit);
                } catch (ValidationException) {
                    continue;
                }
                $result['sizes'][] = ['id' => $size->id, 'label' => $size->translations->firstWhere('locale', $locale)?->label ?? '', 'dimensions_m' => $dimensions];
            }
            $default = collect($result['sizes'])->firstWhere('id', $c['default_size_id'] ?? null);
            if (! $default) {
                $this->fail('Choose an active assigned size with complete width, length, depth and explicit unit.');
            }
            $result['default_size_id'] = $default['id'];
            $result['dimensions_m'] = $default['dimensions_m'];
        } elseif (($c['dimension_source'] ?? null) === 'fixed' && ($c['dimensions_verified'] ?? false)) {
            $result['dimensions_m'] = $this->dimensions($c['width'] ?? null, $c['length'] ?? null, $c['depth'] ?? null, $c['measurement_unit'] ?? null);
        } else {
            $this->fail('Explicit verified dimensions or complete product sizes are required.');
        }
        $texture = $images['texture'] ?? null;
        $variant = $texture ? collect($texture['variants'])->filter(fn ($v) => $v['width'] <= 1600 && $v['height'] <= 1600)->sortByDesc('width')->first() : null;
        if ($texture && ! $variant) {
            $this->fail('Use a processed texture at most 1600px.');
        }
        $result['material'] = ['roughness' => (float) ($c['roughness'] ?? .8), 'metalness' => 0, 'texture' => $variant];

        return $result;
    }

    public function dimensions(mixed $width, mixed $length, mixed $depth, mixed $unit): array
    {
        $factor = ['mm' => .001, 'cm' => .01, 'm' => 1][$unit ?? ''] ?? null;
        if ($factor === null) {
            $this->fail('An explicit supported measurement unit is required.');
        }
        $result = [];
        foreach (['width' => $width, 'length' => $length, 'depth' => $depth] as $key => $value) {
            if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0 || (float) $value * $factor < .000001 || (float) $value * $factor > 100) {
                $this->fail('Complete positive dimensions within the scene budget are required.');
            }
            $result[$key] = (float) $value * $factor;
        }

        return $result;
    }

    private function eligible(MediaAsset $asset, string $type): bool
    {
        return $asset->type === $type && $asset->client_supplied && $asset->processing_state === 'ready' && $asset->approval_state === 'approved' && $asset->active_revision && $asset->variants->contains(fn ($v) => $v->state === 'published' && in_array($v->disk, ['local_published', 'r2_published'], true) && $v->publication_revision === $asset->active_revision);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['viewer_3d_config' => $message]);
    }
}
