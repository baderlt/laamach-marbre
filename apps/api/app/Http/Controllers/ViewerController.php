<?php

namespace App\Http\Controllers;

use App\Catalogue\ProductViewer;
use App\Media\MediaStorage;
use App\Models\MediaVariant;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ViewerController
{
    public function preview(Request $request, Product $product, ProductViewer $viewer)
    {
        Gate::authorize('view', $product);
        $locale = $request->validate(['locale' => ['sometimes', Rule::in(array_column(config('localization.locales'), 'code'))]])['locale'] ?? config('localization.fallback');
        try {
            $config = $viewer->preview($product, $locale);
        } catch (ValidationException) {
            $config = null;
        }
        $copy = config('viewer.messages')[$locale] ?? config('viewer.messages')[config('localization.fallback')];

        return response()->view('admin.viewer-preview', ['config' => $config, 'copy' => $copy, 'product' => $product, 'locale' => $locale, 'direction' => collect(config('localization.locales'))->firstWhere('code', $locale)['direction']])->setStatusCode($config ? 200 : 422)->header('Cache-Control', 'private, no-store');
    }

    public function model(MediaVariant $variant, MediaStorage $storage)
    {
        Gate::authorize('view', $variant->asset);
        abort_unless($variant->asset->type === 'model' && $variant->format === 'glb' && ($variant->metadata['validator_version'] ?? null) === config('viewer.validator_version'), 404);

        return response($storage->variantBytes($variant))->header('Content-Type', 'model/gltf-binary')->header('Cache-Control', 'private, no-store');
    }
}
