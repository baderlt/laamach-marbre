<?php

use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\SiteContent;
use App\Models\User;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Browser-only synthetic fixtures. Requires the existing disposable M3 manifest
// and a separate loopback MySQL instance; never prepares a populated preview.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::assertSafe($app->environment(), config('database'));
$identity = DB::selectOne('SELECT @@port AS port');
if ((int) $identity->port !== 3308 || config('media.private_disk') !== 'local') {
    throw new RuntimeException('Admin browser QA requires isolated loopback port 3308 and local media.');
}
$root = storage_path('framework/testing/m3-browser');
$manifest = json_decode(file_get_contents($root.'/fixture-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
$product = Product::findOrFail($manifest['products'][0]);
$project = Project::findOrFail($manifest['projects'][0]);
if ($product->slug !== 'temporary-product' || $project->slug !== 'synthetic-project') {
    throw new RuntimeException('Unexpected browser business fixtures.');
}
$mode = $argv[1] ?? '';
if ($mode === 'fresh-pending') {
    $name = 'synthetic-m85-pending-'.Str::uuid().'.png';
    $image = imagecreatetruecolor(1600, 1000);
    imagefill($image, 0, 0, imagecolorallocate($image, random_int(30, 180), random_int(30, 180), random_int(30, 180)));
    imagestring($image, 5, 100, 100, $name, imagecolorallocate($image, 250, 250, 250));
    imagepng($image, $root.'/'.$name);
    imagedestroy($image);
    echo $name;
    exit;
}
if (in_array($mode, ['prepare', 'reset-accounts'], true)) {
    foreach (['super_admin' => 'admin', 'content_manager' => 'manager'] as $role => $suffix) {
        $email = 'm85-'.$suffix.'@example.invalid';
        $user = User::where('email', $email)->first()
            ?? ($suffix === 'admin' ? User::where('email', 'm85-changed@example.invalid')->first() : null)
            ?? new User;
        $user->forceFill(['email' => $email, 'name' => 'M8.5 Synthetic '.ucfirst($suffix), 'password' => 'SyntheticOnly-M85-Test1!', 'active' => true, 'role' => $role, 'admin_locale' => 'fr'])->save();
    }
    if ($mode === 'reset-accounts') {
        echo "Synthetic admin accounts reset.\n";
        exit;
    }
    $home = SiteContent::findOrFail('home');
    $copy = $home->translations;
    foreach (['fr' => 'Section française QA', 'ar' => 'قسم عربي تجريبي'] as $locale => $title) {
        $copy[$locale]['sections'] = [['group' => 'services', 'title' => $title, 'body' => 'QA', 'media_asset_id' => $manifest['assets'][0]['id'], 'link' => 'products']];
    }
    $home->forceFill(['translations' => $copy])->save();
    foreach ([1, 2, 3] as $index) {
        $image = imagecreatetruecolor(1600, 1000);
        imagefill($image, 0, 0, imagecolorallocate($image, 70 + $index * 35, 120, 110));
        imagefilledrectangle($image, 100, 100, 900, 600, imagecolorallocate($image, 210, 170, 70 + $index * 35));
        imagepng($image, $root.'/synthetic-m85-'.$index.'.png');
        imagedestroy($image);
    }
    echo "Synthetic admin browser fixtures ready.\n";
    exit;
}
if ($mode !== 'state') {
    throw new RuntimeException('Explicit prepare/state mode required.');
}
$home = SiteContent::findOrFail('home');
$site = SiteContent::findOrFail('site');
$assets = MediaAsset::where('original_filename', 'like', 'synthetic-m85-%')->get();
echo json_encode([
    'baselineOriginalsRetained' => MediaAsset::whereIn('id', array_column($manifest['assets'], 'id'))->get()->every(fn ($asset) => Storage::disk('local')->exists($asset->original_key) && hash_file('sha256', Storage::disk('local')->path($asset->original_key)) === $asset->checksum),
    'home' => ['translations' => $home->translations, 'data' => $home->data],
    'site' => ['translations' => $site->translations, 'data' => $site->data],
    'product' => ['id' => $product->id, 'cover' => $product->cover_media_id, 'gallery' => $product->mediaReferences->filter(fn ($ref) => str_starts_with($ref->slot, 'gallery:'))->sortBy('slot')->pluck('media_asset_id')->values()->all()],
    'category' => ['id' => $manifest['categories'][0], 'cover' => ProductCategory::findOrFail($manifest['categories'][0])->cover_media_id],
    'project' => ['id' => $project->id, 'cover' => $project->cover_media_id, 'gallery' => $project->gallery->map->only(['type', 'media_asset_id', 'poster_media_id'])->all()],
    'assets' => $assets->map(fn ($asset) => ['id' => $asset->id, 'name' => $asset->original_filename, 'approval' => $asset->approval_state, 'processing' => $asset->processing_state, 'revision' => $asset->active_revision, 'originalRetained' => Storage::disk('local')->exists($asset->original_key), 'checksumMatches' => hash_file('sha256', Storage::disk('local')->path($asset->original_key)) === $asset->checksum])->all(),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
