<?php

namespace Tests\Support;

use App\Catalogue\CatalogueWriter;
use App\Media\MediaLibrary;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Str;

class CatalogueFixtures
{
    public static function copy(string $name): array
    {
        return ['fr' => ['name' => $name], 'ar' => ['name' => 'اختبار '.$name]];
    }

    public static function size(string $label = '7 × 20 cm', array $overrides = []): array
    {
        return $overrides + ['width' => 7, 'length' => 20, 'measurement_unit' => 'cm', 'active' => true, 'sort_order' => 0, 'translations' => ['fr' => ['label' => $label], 'ar' => ['label' => $label]]];
    }

    public static function image(User $user, string $root, int $colour): MediaAsset
    {
        if (! app()->environment('testing')) {
            throw new \RuntimeException('Synthetic catalogue fixtures require the test environment.');
        }
        $path = $root.'/'.Str::uuid().'.png';
        $image = imagecreatetruecolor(120, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, $colour, 100, 130));
        imagepng($image, $path);
        $library = app(MediaLibrary::class);
        $asset = $library->ingest($path, 'products/synthetic-'.basename($path), true);
        $revision = $library->process($asset, ['thumb', 'card']);
        $library->approve($asset, $user);
        $library->publish($asset, $revision, $user);

        return $asset->fresh();
    }

    public static function catalogue(User $user): array
    {
        $writer = app(CatalogueWriter::class);
        $parent = $writer->category(null, ['slug' => 'temporary-parent', 'active' => true, 'translations' => self::copy('Temporary parent')], $user);
        $child = $writer->category(null, ['slug' => 'temporary-child', 'parent_id' => $parent->id, 'active' => true, 'translations' => self::copy('Temporary child')], $user);
        $finish = $writer->finish(null, ['slug' => 'temporary-finish', 'active' => true, 'translations' => self::copy('Temporary finish')], $user);

        return compact('parent', 'child', 'finish');
    }
}
