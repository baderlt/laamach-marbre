<!doctype html>
<html lang="{{ $locale }}" dir="{{ $direction }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>3D · {{ $product->reference }}</title>
<style>body{margin:0;background:#faf9f6;color:#222;font:16px/1.7 system-ui,sans-serif}main{max-width:1100px;margin:auto;padding:24px 16px}button,select{font:inherit;min-height:48px;padding:10px 16px;border:1px solid #91846f;background:#f2eadc;color:#222;cursor:pointer}button:focus-visible,select:focus-visible,a:focus-visible,canvas:focus-visible{outline:3px solid #265d83;outline-offset:4px}.viewer-toolbar{display:flex;flex-wrap:wrap;gap:8px;margin-block:16px}#viewer-host{height:440px;background:#f1eee7}#viewer-host canvas{width:100%;height:100%;display:block}a{color:#514226}[data-admin-viewer]:fullscreen{background:#faf9f6;padding:16px;overflow:auto}label{display:grid;gap:8px}#viewer-status{white-space:pre-wrap}@media(max-width:767px){#viewer-host{height:320px}main{padding:16px}}</style></head>
<body><main><a href="/admin/products">{{ $copy['back'] }}</a><h1>{{ $product->translations->firstWhere('locale', $locale)?->name ?? $product->reference }}</h1><p><bdi>{{ $product->reference }}</bdi> · {{ $copy['preview'] }} {{ $product->viewer_3d_enabled ? '' : $copy['disabled'] }}</p>
@if ($config)
<section data-admin-viewer><p id="viewer-status" role="status" aria-live="polite"></p><div id="viewer-host" data-viewer-host></div></section>
<script id="viewer-data" type="application/json">{!! json_encode(['config' => $config, 'locale' => $locale], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
<script type="module" src="{{ asset('js/viewer/preview.js') }}"></script>
@else <p role="alert">{{ $copy['unavailable'] }}</p> @endif
</main></body></html>
