<?php

// Test-only browser preparation, not a production seeder or application command.
use App\Catalogue\CatalogueWriter;
use App\Editorial\SiteContentWriter;
use App\Media\MediaLibrary;
use App\Media\MediaPath;
use App\Models\Finish;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\SiteContent;
use App\Models\User;
use App\Projects\ProjectWriter;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CatalogueFixtures as Fixtures;
use Tests\Support\ProjectFixtures;

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
    foreach ($data['editorial'] ?? [] as $key) {
        if ($record = SiteContent::find($key)) {
            $record->mediaReferences()->delete();
            $record->delete();
        }
    }
    foreach ($data['projects'] ?? [] as $id) {
        if ($record = Project::find($id)) {
            app(ProjectWriter::class)->delete($record, $user);
        }
    }
    foreach ($data['project_categories'] ?? [] as $id) {
        if ($record = ProjectCategory::find($id)) {
            app(ProjectWriter::class)->delete($record, $user);
        }
    }
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
if (ProductCategory::exists() || ProjectCategory::exists() || Project::exists() || SiteContent::exists() || File::exists($manifest)) {
    throw new RuntimeException('Prepare requires an empty dedicated test catalogue and no pending fixture manifest.');
}
File::ensureDirectoryExists($root);
$data = DB::transaction(function () use ($writer, $root, $base) {
    $user = User::factory()->create(['active' => true, 'role' => 'super_admin']);
    $fixture = Fixtures::catalogue($user);
    $second = $writer->finish(null, ['slug' => 'temporary-second-finish', 'active' => true, 'translations' => Fixtures::copy('Second temporary finish')], $user);
    $cover = Fixtures::image($user, $root, 50);
    $dedicated = Fixtures::image($user, $root, 190);
    $extraAssets = [];
    if (in_array('--layout-client-media', $GLOBALS['argv'], true)) {
        // Optional local comparison only: real photos, explicitly synthetic test business records.
        $clientRoot = dirname($base, 2).'/client-assets';
        $mapping = json_decode(File::get($clientRoot.'/assets-map.json'), true, 32, JSON_THROW_ON_ERROR);
        $layoutImages = [];
        foreach (['products/hajar-jbal-taza/cover.jpeg', 'products/hajar-jbal-taza/gallery/01.jpeg'] as $index => $source) {
            $row = collect($mapping)->firstWhere('source', $source);
            if (! $row || hash_file('sha256', $clientRoot.'/'.$source) !== $row['sha256']) {
                throw new RuntimeException('Missing reviewed layout photograph.');
            }
            $library = app(MediaLibrary::class);
            $asset = $library->ingest($clientRoot.'/'.$source, 'products/synthetic-layout-'.$index.'.jpeg', true);
            $revision = $library->process($asset, ['thumb', 'card', 'gallery']);
            $library->approve($asset, $user);
            $library->publish($asset, $revision, $user);
            $layoutImages[] = $asset;
            $extraAssets[] = ['id' => $asset->id];
        }
    }
    $attributes = ['slug' => 'temporary-product', 'category_id' => $fixture['child']->id, 'status' => 'published', 'translations' => Fixtures::copy('Temporary product'), 'units' => ['m2', 'piece'], 'cover_media_id' => $cover->id, 'gallery' => [$dedicated->id], 'sizes' => [Fixtures::size('Dedicated size', ['cover_media_id' => $dedicated->id]), Fixtures::size('Fallback size', ['sort_order' => 1])], 'custom_size_allowed' => true, 'custom_measurement_unit' => 'cm', 'min_width' => 5, 'max_width' => 40, 'min_length' => 10, 'max_length' => 60, 'finishes' => [['finish_id' => $fixture['finish']->id, 'active' => true, 'is_default' => true, 'sort_order' => 0], ['finish_id' => $second->id, 'active' => true, 'is_default' => false, 'sort_order' => 1]]];
    $product = $writer->product(null, isset($layoutImages) ? array_replace($attributes, ['featured' => true, 'cover_media_id' => $layoutImages[0]->id, 'gallery' => [$layoutImages[1]->id], 'sizes' => [Fixtures::size('Dedicated size', ['cover_media_id' => $layoutImages[1]->id]), Fixtures::size('Fallback size', ['sort_order' => 1])]]) : $attributes, $user);
    $noImage = $writer->product(null, array_replace($attributes, ['slug' => 'temporary-no-image', 'finishes' => [], 'cover_media_id' => null, 'gallery' => [], 'sizes' => [Fixtures::size('Fallback size')], 'custom_size_allowed' => false, 'custom_measurement_unit' => null, 'min_width' => null, 'max_width' => null, 'min_length' => null, 'max_length' => null]), $user);
    $draft = $writer->product(null, array_replace($attributes, ['slug' => 'temporary-draft', 'status' => 'draft']), $user);
    $usage = ProjectFixtures::category($user);
    $otherUsage = ProjectFixtures::category($user, 'second-usage');
    $video = ProjectFixtures::video($user, $root);
    $projectData = ['slug' => 'synthetic-project', 'status' => 'published', 'categories' => [$usage->id, $otherUsage->id], 'translations' => ProjectFixtures::copy(), 'cover_media_id' => $cover->id, 'location' => 'Synthetic location', 'year' => 2024, 'area' => 12, 'area_unit' => 'm2', 'products' => [['product_id' => $product->id, 'sort_order' => 0], ['product_id' => $draft->id, 'sort_order' => 1]], 'gallery' => [['type' => 'image', 'media_asset_id' => $dedicated->id], ['type' => 'video', 'media_asset_id' => $video->id, 'poster_media_id' => $cover->id, 'captions' => ['fr' => 'Vidéo synthétique', 'ar' => 'فيديو اختبار']]]];
    $projectWriter = app(ProjectWriter::class);
    $project = $projectWriter->save(null, $projectData, $user);
    $noCover = $projectWriter->save(null, array_replace($projectData, ['slug' => 'synthetic-no-cover', 'cover_media_id' => null, 'categories' => [$otherUsage->id], 'products' => [], 'location' => null, 'year' => null, 'area' => null, 'area_unit' => null, 'gallery' => [['type' => 'video', 'media_asset_id' => $video->id]]]), $user);
    $draftProject = $projectWriter->save(null, array_replace($projectData, ['slug' => 'synthetic-draft', 'status' => 'draft']), $user);
    $project->forceFill(['featured' => true])->save();
    $editorial = [];
    $headings = ['home' => ['La pierre, naturellement.', 'جمال الحجر، بطبيعته.'], 'products' => ['Pierre & marbre', 'منتجات الحجر والرخام'], 'projects' => ['Nos projets', 'مشاريعنا'], 'services' => ['Les services LAAMACH MARBRE', 'خدمات LAAMACH MARBRE'], 'about' => ['La matière au cœur de chaque espace.', 'المادة في قلب كل مساحة.'], 'contact' => ['Échangeons autour de votre projet.', 'لنتحدث عن مشروعك.']];
    foreach (['home', 'products', 'projects', 'services', 'about', 'contact'] as $key) {
        $translations = [];
        foreach (config('localization.locales') as $locale) {
            $translations[$locale['code']] = ['title' => $headings[$key][$locale['code'] === 'ar' ? 1 : 0], 'seo_title' => 'LAAMACH MARBRE — QA '.$key];
        }
        app(SiteContentWriter::class)->save(null, ['key' => $key, 'published' => true, 'translations' => $translations, 'data' => []], $user);
        $editorial[] = $key;
    }

    // Fictional destination confined to disposable Chromium fixtures, never real business data.
    app(SiteContentWriter::class)->save(null, ['key' => 'site', 'published' => true, 'translations' => [], 'data' => ['whatsapp' => ['number' => '12025550123', 'enabled' => true, 'material' => true, 'similar' => true, 'application' => true]]], $user);
    $editorial[] = 'site';

    return ['user' => $user->id, 'products' => [$product->id, $noImage->id, $draft->id], 'finishes' => [$fixture['finish']->id, $second->id], 'categories' => [$fixture['child']->id, $fixture['parent']->id], 'projects' => [$project->id, $noCover->id, $draftProject->id], 'project_categories' => [$usage->id, $otherUsage->id], 'editorial' => $editorial, 'assets' => [['id' => $cover->id], ['id' => $dedicated->id], ['id' => $video->id], ...$extraAssets]];
});
File::put($manifest, json_encode($data, JSON_THROW_ON_ERROR));
echo "Synthetic test-only catalogue prepared for Chromium. Stop QA servers and use cleanup before the next database refresh.\n";
