<?php

// Disposable M6 browser fixtures. Never a production seeder.
use App\Catalogue\CatalogueWriter;
use App\Media\MediaLibrary;
use App\Media\ModelLibrary;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\User;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\CatalogueFixtures;
use Tests\Support\ModelFixtures;

$base = dirname(__DIR__, 2);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::assertSafe($app->environment(), config('database'));
if (DB::selectOne('SELECT DATABASE() AS name')->name !== TestDatabaseGuard::DATABASE || config('media.private_disk') !== 'local' || config('media.published_disk') !== 'local_published') {
    throw new RuntimeException('Dedicated disposable database/local disks only.');
}
$root = storage_path('framework/testing/m3-browser');
$path = $root.'/fixture-manifest.json';
$data = json_decode(File::get($path), true, 32, JSON_THROW_ON_ERROR);
if (in_array($argv[1] ?? 'add', ['record-upload', 'verify-upload'], true)) {
    if (isset($data['viewer_upload_sha256'])) {
        $asset = MediaAsset::where('checksum', $data['viewer_upload_sha256'])->where('original_filename', 'synthetic-admin-upload.glb')->first();
        if ($asset && ! collect($data['assets'])->contains('id', $asset->id)) {
            $data['assets'][] = ['id' => $asset->id];
        }
        File::put($path, json_encode($data, JSON_THROW_ON_ERROR));
        if (($argv[1] ?? '') === 'verify-upload' && (! $asset || $asset->type !== 'model' || $asset->processing_state !== 'ready' || $asset->approval_state !== 'pending' || $asset->active_revision || $asset->variants()->where('state', 'published')->exists())) {
            throw new RuntimeException('The uploaded synthetic model must remain validated, private and unapproved.');
        }
    }
    echo "Synthetic upload tracked for guarded cleanup.\n";
    exit;
}
if (! empty($data['viewer_added'])) {
    echo "M6 synthetic fixtures already present.\n";
    exit;
}
$data = DB::transaction(function () use ($data, $root) {
    $user = User::findOrFail($data['user']);
    // Fixed credentials exist only inside this guarded, disposable QA setup.
    $user->forceFill(['email' => 'viewer-qa@example.invalid', 'password' => 'SyntheticOnly-M6-Only1!'])->save();
    $models = [];
    foreach ([.35, .85] as $index => $tone) {
        [$j,$bin] = ModelFixtures::document(true);
        $j['materials'][0]['pbrMetallicRoughness']['baseColorFactor'][0] = $tone;
        $file = $root.'/synthetic-model-'.$index.'.glb';
        File::put($file, ModelFixtures::pack($j, $bin));
        $asset = app(ModelLibrary::class)->ingest($file, 'products/synthetic-model-'.$index.'.glb', true);
        if ($index === 0) {
            app(MediaLibrary::class)->approve($asset, $user);
            app(MediaLibrary::class)->publish($asset, $asset->variants()->firstOrFail()->revision, $user);
        }
        $models[] = $asset;
        $data['assets'][] = ['id' => $asset->id];
    }
    [$j,$bin] = ModelFixtures::document();
    $j['materials'][0]['pbrMetallicRoughness']['baseColorFactor'] = [.25, .55, .7, 1];
    $upload = ModelFixtures::pack($j, $bin);
    File::put($root.'/synthetic-admin-upload.glb', $upload);
    $data['viewer_upload_sha256'] = hash('sha256', $upload);
    $writer = app(CatalogueWriter::class);
    $category = Product::findOrFail($data['products'][0])->category_id;
    $base = ['category_id' => $category, 'status' => 'published', 'translations' => CatalogueFixtures::copy('Synthetic 3D QA only'), 'units' => ['piece'], 'cover_media_id' => $data['assets'][0]['id']];
    $configurations = [
        'synthetic-glb' => ['viewer_3d_config' => ['mode' => 'uploaded_glb', 'model_media_id' => $models[0]->id]],
        'synthetic-slab' => ['sizes' => [CatalogueFixtures::size('Mapped size A', ['width' => 70, 'length' => 200, 'thickness' => 20, 'measurement_unit' => 'mm']), CatalogueFixtures::size('Mapped size B', ['width' => 100, 'length' => 300, 'thickness' => 15, 'measurement_unit' => 'mm', 'sort_order' => 1])]],
        'synthetic-block' => ['viewer_3d_config' => ['mode' => 'procedural', 'geometry' => 'block', 'dimension_source' => 'fixed', 'dimensions_verified' => true, 'width' => 60, 'length' => 40, 'depth' => 20, 'measurement_unit' => 'cm']],
        'synthetic-private-viewer' => ['status' => 'draft', 'viewer_3d_config' => ['mode' => 'uploaded_glb', 'model_media_id' => $models[1]->id]],
    ];
    foreach ($configurations as $slug => $overrides) {
        $input = array_replace($base, ['slug' => $slug, 'viewer_3d_enabled' => $slug !== 'synthetic-slab'], $overrides);
        $product = $writer->product(null, $input, $user);
        if ($slug === 'synthetic-slab') {
            $size = $product->sizes()->firstOrFail();
            $input['sizes'] = $product->sizes->map(fn ($s) => ['id' => $s->id, 'width' => $s->width, 'length' => $s->length, 'thickness' => $s->thickness, 'height' => $s->height, 'measurement_unit' => $s->measurement_unit, 'active' => true, 'sort_order' => $s->sort_order, 'cover_media_id' => null, 'translations' => $s->translations->mapWithKeys(fn ($t) => [$t->locale => ['label' => $t->label]])->all()])->all();
            $input['viewer_3d_enabled'] = true;
            $input['viewer_3d_config'] = ['mode' => 'procedural', 'geometry' => 'slab', 'dimension_source' => 'product_sizes', 'default_size_id' => $size->id, 'texture_media_id' => $data['assets'][0]['id']];
            $product = $writer->product($product, $input, $user);
        }
        if ($slug === 'synthetic-private-viewer') {
            $data['viewer_private_product'] = $product->id;
        }
        $data['products'][] = $product->id;
    }
    $data['viewer_added'] = true;

    return $data;
});
File::put($path, json_encode($data, JSON_THROW_ON_ERROR));
echo "Synthetic GLB, block, slab and private admin-preview fixtures added.\n";
