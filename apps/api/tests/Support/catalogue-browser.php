<?php

// Test-only browser preparation, not a production seeder or application command.
use App\Catalogue\CatalogueWriter;
use App\Media\MediaPath;
use App\Models\Finish;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CatalogueFixtures as Fixtures;

$base = dirname(__DIR__, 2);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::assertSafe($app->environment(), config('database'));
if (DB::selectOne('SELECT DATABASE() AS name')->name !== TestDatabaseGuard::DATABASE || config('media.private_disk') !== 'local' || config('media.published_disk') !== 'local_published') {
    throw new RuntimeException('Browser fixtures require the dedicated test database and local disks.');
}
$root = storage_path('framework/testing/m3-browser');
$manifest = $root.'/fixture-manifest.json';
$writer = app(CatalogueWriter::class);
if (($argv[1] ?? 'prepare') === 'cleanup') {
    $data = json_decode(File::get($manifest), true, 32, JSON_THROW_ON_ERROR);
    $user = User::findOrFail($data['user']);
    foreach ($data['products'] as $id) {
        if ($record = Product::find($id)) {
            $writer->delete($record, $user);
        }
    }
    foreach ($data['finishes'] as $id) {
        if ($record = Finish::find($id)) {
            $writer->delete($record, $user);
        }
    }
    foreach ($data['categories'] as $id) {
        if ($record = ProductCategory::find($id)) {
            $writer->delete($record, $user);
        }
    }
    foreach ($data['assets'] as $item) {
        $asset = MediaAsset::findOrFail($item['id']);
        if ($asset->references()->exists() || $asset->imports()->exists() || ! str_starts_with($asset->original_filename, 'synthetic-')) {
            throw new RuntimeException('Synthetic asset is still referenced.');
        }
        MediaPath::assertOriginal($asset->original_key, $asset->id, $asset->checksum);
        Storage::disk('local')->delete($asset->original_key);
        foreach ($asset->variants as $variant) {
            MediaPath::assertVariant($variant->object_key, $asset->id, $variant->profile_version, $variant->checksum, $variant->format);
            foreach (['local', 'local_published'] as $disk) {
                Storage::disk($disk)->delete($variant->object_key);
            }
        }
    }
    File::delete($manifest);
    echo "Synthetic catalogue rows/objects removed. Run the guarded foundation tests to reset remaining test media/users.\n";
    exit;
}
if (ProductCategory::exists() || File::exists($manifest)) {
    throw new RuntimeException('Prepare requires an empty dedicated test catalogue and no pending fixture manifest.');
}
File::ensureDirectoryExists($root);
$data = DB::transaction(function () use ($writer, $root) {
    $user = User::factory()->create(['active' => true, 'role' => 'super_admin']);
    $fixture = Fixtures::catalogue($user);
    $second = $writer->finish(null, ['slug' => 'temporary-second-finish', 'active' => true, 'translations' => Fixtures::copy('Second temporary finish')], $user);
    $cover = Fixtures::image($user, $root, 50);
    $dedicated = Fixtures::image($user, $root, 190);
    $attributes = ['slug' => 'temporary-product', 'category_id' => $fixture['child']->id, 'status' => 'published', 'translations' => Fixtures::copy('Temporary product'), 'units' => ['m2', 'piece'], 'cover_media_id' => $cover->id, 'gallery' => [$dedicated->id], 'sizes' => [Fixtures::size('Dedicated size', ['cover_media_id' => $dedicated->id]), Fixtures::size('Fallback size', ['sort_order' => 1])], 'custom_size_allowed' => true, 'custom_measurement_unit' => 'cm', 'min_width' => 5, 'max_width' => 40, 'min_length' => 10, 'max_length' => 60, 'finishes' => [['finish_id' => $fixture['finish']->id, 'active' => true, 'is_default' => true, 'sort_order' => 0], ['finish_id' => $second->id, 'active' => true, 'is_default' => false, 'sort_order' => 1]]];
    $product = $writer->product(null, $attributes, $user);
    $noImage = $writer->product(null, array_replace($attributes, ['slug' => 'temporary-no-image', 'cover_media_id' => null, 'gallery' => [], 'sizes' => [Fixtures::size('Fallback size')], 'custom_size_allowed' => false, 'custom_measurement_unit' => null, 'min_width' => null, 'max_width' => null, 'min_length' => null, 'max_length' => null]), $user);
    $draft = $writer->product(null, array_replace($attributes, ['slug' => 'temporary-draft', 'status' => 'draft']), $user);

    return ['user' => $user->id, 'products' => [$product->id, $noImage->id, $draft->id], 'finishes' => [$fixture['finish']->id, $second->id], 'categories' => [$fixture['child']->id, $fixture['parent']->id], 'assets' => [['id' => $cover->id], ['id' => $dedicated->id]]];
});
File::put($manifest, json_encode($data, JSON_THROW_ON_ERROR));
echo "Synthetic test-only catalogue prepared for Chromium. Stop QA servers and use cleanup before the next database refresh.\n";
