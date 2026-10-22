<?php

namespace Tests\Feature;

use App\Filament\Resources\MediaAssetResource\ManageMediaAssets;
use App\Filament\Resources\MediaForms;
use App\Media\AssetImporter;
use App\Media\ImageProcessor;
use App\Media\MediaConfiguration;
use App\Media\MediaLibrary;
use App\Media\MediaPath;
use App\Media\MediaStorage;
use App\Media\PublishedMedia;
use App\Models\MediaAsset;
use App\Models\MediaReference;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/'.getmypid().'/fixtures/'.Str::uuid());
        File::makeDirectory($this->root.'/products', 0755, true);
        foreach (['local', 'local_published'] as $disk) {
            Storage::set($disk, Storage::build(['driver' => 'local', 'root' => $this->root.'/disks/'.$disk, 'throw' => true]));
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function fixture(string $name = 'products/sample.png', int $width = 100, int $height = 60, int $color = 80): string
    {
        $path = $this->root.'/'.$name;
        File::ensureDirectoryExists(dirname($path));
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, $color, 110, 140));
        imagepng($image, $path);

        return $path;
    }

    private function asset(bool $supplied = true): MediaAsset
    {
        return app(MediaLibrary::class)->ingest($this->fixture(), 'products/sample.png', $supplied);
    }

    private function admin(): User
    {
        return User::factory()->create(['active' => true, 'role' => 'super_admin']);
    }

    private function manifest(array $overrides = [], string $source = 'products/sample.png'): void
    {
        File::put($this->root.'/assets-map.json', json_encode([array_replace(['source' => $source, 'target_type' => 'product', 'target_key' => 'future-reference', 'role' => 'product_cover', 'order' => 1, 'translations' => ['fr' => ['alt' => 'Client description']]], $overrides)], JSON_THROW_ON_ERROR));
    }

    public function test_ingest_is_content_based_private_and_deduplicated(): void
    {
        $path = $this->fixture('products/not-an-extension.bin');
        $library = app(MediaLibrary::class);
        $asset = $library->ingest($path, 'products/not-an-extension.bin');
        $again = $library->ingest($path, 'products/other.png');
        $this->assertTrue(Str::isUuid($asset->id));
        $this->assertSame($asset->id, $again->id);
        $this->assertSame('image/png', $asset->mime);
        $this->assertSame(hash_file('sha256', $path), $asset->checksum);
        $this->assertFalse($asset->client_supplied);
        $this->assertSame('pending', $asset->approval_state);
        Storage::disk('local')->assertExists($asset->original_key);
        $this->assertSame([], Storage::disk('local_published')->allFiles());
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_real_webp_and_optional_avif_encoding_and_profile_generation(): void
    {
        $processor = app(ImageProcessor::class);
        $this->assertTrue($processor->capabilities()['webp']);
        $asset = $this->asset();
        $revision = app(MediaLibrary::class)->process($asset);
        $this->assertSame($revision, app(MediaLibrary::class)->process($asset));
        $this->assertDatabaseCount('media_variants', 2);
        foreach ($asset->variants as $variant) {
            $bytes = app(MediaStorage::class)->variantBytes($variant);
            $this->assertSame('image/webp', getimagesizefromstring($bytes)['mime']);
            $this->assertSame($variant->checksum, hash('sha256', $bytes));
            $this->assertSame(strlen($bytes), $variant->bytes);
            $this->assertSame(100, $variant->width); // Never upscale.
            $this->assertSame('private', $variant->state);
            $this->assertTrue($variant->metadata['metadata_stripped']);
            $this->assertStringNotContainsString('EXIF', $bytes);
        }
        if ($processor->capabilities()['avif']) {
            config(['media.formats' => ['webp', 'avif']]);
            $revision = app(MediaLibrary::class)->process($asset, ['thumb']);
            $avif = $asset->variants()->where('revision', $revision)->where('format', 'avif')->firstOrFail();
            $this->assertSame('image/avif', getimagesizefromstring(app(MediaStorage::class)->variantBytes($avif))['mime']);
        }
    }

    public function test_jpeg_camera_orientations_and_mirrors_survive_metadata_stripping(): void
    {
        $image = imagecreatetruecolor(80, 40);
        foreach ([[0, 0, 255, 0, 0], [40, 0, 0, 255, 0], [0, 20, 0, 0, 255], [40, 20, 255, 255, 0]] as [$x, $y, $r, $g, $b]) {
            imagefilledrectangle($image, $x, $y, $x + 39, $y + 19, imagecolorallocate($image, $r, $g, $b));
        }
        ob_start();
        imagejpeg($image, null, 100);
        $jpeg = ob_get_clean();
        $expected = [1 => [0, 1, 2, 3], 2 => [1, 0, 3, 2], 3 => [3, 2, 1, 0], 4 => [2, 3, 0, 1], 5 => [0, 2, 1, 3], 6 => [2, 0, 3, 1], 7 => [3, 1, 2, 0], 8 => [1, 3, 0, 2]];
        $processor = app(ImageProcessor::class);
        foreach ([true, false] as $little) {
            foreach ($expected as $orientation => $corners) {
                $short = $little ? 'v' : 'n';
                $long = $little ? 'V' : 'N';
                $tiff = ($little ? 'II' : 'MM').pack($short, 42).pack($long, 8).pack($short, 1).pack($short, 0x112).pack($short, 3).pack($long, 1).pack($short, $orientation)."\0\0".pack($long, 0);
                $exif = "Exif\0\0".$tiff;
                $bytes = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
                $path = $this->root.'/orientation.jpg';
                File::put($path, $bytes);
                $dimensions = $orientation >= 5 ? [40, 80] : [80, 40];
                $info = $processor->inspect($path);
                $this->assertSame($dimensions, [$info['width'], $info['height']]);
                $variant = iterator_to_array($processor->derivatives($bytes, ['thumb']))[0];
                $this->assertSame($dimensions, [$variant['width'], $variant['height']]);
                $decoded = imagecreatefromstring($variant['content']);
                $actual = [];
                foreach ([[0.25, 0.25], [0.75, 0.25], [0.25, 0.75], [0.75, 0.75]] as [$x, $y]) {
                    $rgb = imagecolorsforindex($decoded, imagecolorat($decoded, (int) ($x * imagesx($decoded)), (int) ($y * imagesy($decoded))));
                    $actual[] = $rgb['red'] > 128 ? ($rgb['green'] > 128 ? 3 : 0) : ($rgb['green'] > 128 ? 1 : 2);
                }
                $this->assertSame($corners, $actual, "Orientation $orientation");
                $this->assertStringNotContainsString('Exif', $variant['content']);
                $this->assertSame(hash('sha256', $bytes), hash_file('sha256', $path));
            }
        }
        // A truncated/untrusted TIFF offset cannot trigger an out-of-bounds read.
        $badExif = "Exif\0\0II\x2a\0\xff\xff\xff\x7f";
        $decoded = $processor->decode(substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($badExif) + 2).$badExif.substr($jpeg, 2));
        $this->assertSame([80, 40], [imagesx($decoded), imagesy($decoded)]);
    }

    public function test_admin_preview_matches_the_published_revision_until_a_new_one_is_published(): void
    {
        $asset = $this->asset();
        $library = app(MediaLibrary::class);
        $first = $library->process($asset);
        $admin = $this->admin();
        $library->approve($asset, $admin);
        $library->publish($asset, $first, $admin);
        $originalPreview = $this->actingAs($admin)->get(route('media.preview', $asset))->assertOk()->getContent();
        config(['media.profiles.thumb.edge' => 20]);
        $next = $library->process($asset);
        $this->assertNotSame($first, $next);
        $this->assertSame($originalPreview, $this->get(route('media.preview', $asset))->assertOk()->getContent());
        $library->publish($asset, $next, $admin);
        $this->assertNotSame($originalPreview, $this->get(route('media.preview', $asset))->assertOk()->getContent());
    }

    public function test_reuploading_existing_bytes_uses_the_current_encoder_without_implicit_publication(): void
    {
        config(['media.encoder_version' => 'gd-1']);
        $asset = $this->asset();
        $library = app(MediaLibrary::class);
        $first = $library->process($asset, ['thumb', 'card', 'gallery']);
        $admin = $this->admin();
        $library->approve($asset, $admin);
        $library->publish($asset, $first, $admin);
        $this->actingAs($admin);
        config(['media.encoder_version' => 'gd-2-exif']);
        $input = ['upload' => new UploadedFile($this->root.'/products/sample.png', 'sample.png', test: true), 'client_supplied' => true, 'approve_and_publish' => false];
        $this->assertSame($asset->id, MediaForms::upload($input));
        $this->assertSame($first, $asset->fresh()->active_revision);
        $this->assertSame(2, $asset->variants()->distinct()->count('revision'));
        $input['approve_and_publish'] = true;
        $this->assertSame($asset->id, MediaForms::upload($input));
        $this->assertNotSame($first, $asset->fresh()->active_revision);
        $this->assertSame($asset->checksum, hash('sha256', app(MediaStorage::class)->originalBytes($asset)));
    }

    public function test_publication_requires_approval_and_explicit_provenance(): void
    {
        $asset = $this->asset(false);
        $revision = app(MediaLibrary::class)->process($asset);
        $admin = $this->admin();
        try {
            app(MediaLibrary::class)->approve($asset, $admin);
            $this->fail('Unconfirmed provenance approved.');
        } catch (ValidationException) {
            $this->assertSame('pending', $asset->fresh()->approval_state);
        }
        $this->expectException(ValidationException::class);
        app(MediaLibrary::class)->publish($asset, $revision, $admin);
    }

    public function test_publication_has_stable_public_urls_immutable_cache_and_new_versions(): void
    {
        $asset = $this->asset();
        $library = app(MediaLibrary::class);
        $revision = $library->process($asset);
        $admin = $this->admin();
        $privateKey = $asset->original_key;
        $this->get('/media/'.$privateKey)->assertNotFound();
        $this->get('/storage/'.$privateKey)->assertNotFound();
        $this->assertSame([], app(PublishedMedia::class)->forAsset($asset));
        $library->approve($asset, $admin);
        $library->publish($asset, $revision, $admin);
        $first = app(PublishedMedia::class)->forAsset($asset->fresh());
        $this->assertCount(2, $first);
        $response = $this->get($first[0]['url'])->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=31536000', $response->headers->get('Cache-Control'));
        $firstBytes = $response->getContent();
        $library->publish($asset, $revision, $admin);
        $this->assertSame($first, app(PublishedMedia::class)->forAsset($asset->fresh()));
        config(['media.profiles.card.edge' => 50]);
        $secondRevision = $library->process($asset);
        $library->publish($asset, $secondRevision, $admin);
        $second = app(PublishedMedia::class)->forAsset($asset->fresh());
        $this->assertNotSame($revision, $secondRevision);
        $this->assertNotSame($first[0]['url'], $second[0]['url']);
        $this->assertSame($firstBytes, $this->get($first[0]['url'])->assertOk()->getContent());
        $this->assertCount(4, Storage::disk('local_published')->allFiles());
        $library->unpublish($asset, $admin);
        $this->assertSame([], app(PublishedMedia::class)->forAsset($asset->fresh()));
        $this->get($first[0]['url'])->assertOk(); // Retention/caching semantics are honest.
        Storage::disk('local_published')->assertMissing($privateKey);
    }

    public function test_changed_source_is_a_new_asset_and_key_without_overwriting_history(): void
    {
        $one = $this->asset();
        $two = app(MediaLibrary::class)->ingest($this->fixture('products/replacement.png', color: 190), 'products/replacement.png', true);
        $library = app(MediaLibrary::class);
        $admin = $this->admin();
        foreach ([$one, $two] as $asset) {
            $revision = $library->process($asset, ['thumb']);
            $library->approve($asset, $admin);
            $library->publish($asset, $revision, $admin);
        }
        $this->assertNotSame($one->id, $two->id);
        $this->assertNotSame($one->variants()->first()->object_key, $two->variants()->first()->object_key);
        $this->assertCount(2, Storage::disk('local_published')->allFiles());
    }

    public static function badPaths(): array
    {
        return array_map(fn ($path) => [$path], ['../x.png', '/x.png', 'products/../x.png', 'products//x.png', 'C:\\x.png', 'products/%2e%2e/x.png', "products/x\0.png", 'products/x.png?key=1']);
    }

    #[DataProvider('badPaths')]
    public function test_traversal_and_key_injection_paths_are_rejected(string $path): void
    {
        $this->expectException(ValidationException::class);
        MediaPath::relative($path);
    }

    public static function badImages(): array
    {
        return [['spoof'], ['svg'], ['truncated'], ['bytes'], ['pixels'], ['dimensions']];
    }

    #[DataProvider('badImages')]
    public function test_spoofed_corrupt_and_oversized_images_are_rejected(string $case): void
    {
        $path = $this->fixture();
        match ($case) {
            'spoof' => File::put($path, '<?php echo "not an image";'),
            'svg' => File::put($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'truncated' => File::put($path, substr(File::get($path), 0, 45)),
            'bytes' => config(['media.max_bytes' => 10]),
            'pixels' => config(['media.max_pixels' => 100]),
            'dimensions' => config(['media.max_dimension' => 20]),
        };
        $this->expectException(ValidationException::class);
        app(ImageProcessor::class)->inspect($path);
    }

    public function test_memory_budget_is_checked_before_decoding(): void
    {
        config(['media.memory_budget' => 1024]);
        $this->expectException(ValidationException::class);
        app(ImageProcessor::class)->inspect($this->fixture());
    }

    public function test_dry_run_has_zero_database_and_filesystem_mutations(): void
    {
        $this->fixture();
        $this->manifest();
        $before = DB::table('media_assets')->count();
        $report = app(AssetImporter::class)->run($this->root);
        $this->assertSame('dry-run', $report['mode']);
        $this->assertSame(1, $report['accepted']);
        $this->assertSame(0, $report['failed']);
        $this->assertDatabaseCount('media_assets', $before);
        foreach (['media_imports', 'media_variants', 'media_translations'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('local_published')->allFiles());
    }

    public function test_apply_is_idempotent_preserves_editorial_changes_and_tracks_duplicate_mappings(): void
    {
        $this->fixture();
        $this->manifest();
        $importer = app(AssetImporter::class);
        $this->assertSame(1, $importer->run($this->root, true, true)['accepted']);
        $asset = MediaAsset::firstOrFail();
        $asset->translations()->first()->update(['alt' => 'Edited later']);
        $this->manifest(['translations' => ['fr' => ['alt' => 'New manifest text']]]);
        $this->assertSame(1, $importer->run($this->root, true, true)['skipped']);
        $this->assertSame('Edited later', $asset->translations()->first()->alt);
        File::copy($this->root.'/products/sample.png', $this->root.'/products/duplicate.png');
        $this->manifest(source: 'products/duplicate.png');
        $report = $importer->run($this->root, true, true);
        $this->assertSame(0, $report['failed']);
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('media_imports', 2);
        $this->assertDatabaseCount('media_variants', 2);
        $this->assertSame('pending', $asset->fresh()->approval_state);
        $this->fixture(color: 180);
        $this->manifest();
        $this->assertSame(1, $importer->run($this->root, true, true)['failed']);
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_unmapped_sources_are_reported_and_not_inferred_from_filenames(): void
    {
        $this->fixture();
        $report = app(AssetImporter::class)->run($this->root, true);
        $this->assertSame(1, $report['skipped']);
        $this->assertNotNull($report['files'][0]['checksum']);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public static function badManifest(): array
    {
        return [[['source' => '../outside.png']], [['disk' => 'r2_published']], [['target_type' => 'brand']], [['role' => 'project_cover']], [['translations' => ['en' => ['alt' => 'Disabled']]]], [['focal' => ['x' => 2, 'y' => 0]]], [['target_key' => '../injection']], [['crop' => '<script>']]];
    }

    #[DataProvider('badManifest')]
    public function test_manifest_rejects_unsafe_unknown_or_conflicting_fields(array $overrides): void
    {
        $this->fixture();
        $this->manifest($overrides);
        $this->expectException(ValidationException::class);
        app(AssetImporter::class)->run($this->root, true);
    }

    public function test_import_command_defaults_to_dry_run_and_requires_explicit_apply(): void
    {
        $this->fixture();
        $this->manifest();
        $this->artisan('laamach:media-import', ['directory' => $this->root])->assertSuccessful();
        $this->assertDatabaseCount('media_assets', 0);
        $this->artisan('laamach:media-import', ['directory' => $this->root, '--apply' => true, '--client-supplied' => true])->assertSuccessful();
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_private_preview_and_download_are_authorized_and_originals_never_public(): void
    {
        $asset = $this->asset();
        app(MediaLibrary::class)->process($asset);
        $this->get(route('media.preview', $asset))->assertRedirect('/admin/login');
        $this->getJson(route('media.original', $asset))->assertUnauthorized();
        $inactive = User::factory()->create();
        $this->actingAs($inactive)->get(route('media.preview', $asset))->assertForbidden();
        $manager = User::factory()->create(['active' => true]);
        $this->actingAs($manager)->get(route('media.preview', $asset))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('media.original', $asset))->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
        $this->get('/media/'.$asset->variants()->first()->object_key)->assertNotFound();
        $this->assertFalse(Gate::forUser($manager)->allows('approve', $asset));
        $this->assertFalse(Gate::forUser($manager)->allows('publish', $asset));
    }

    public function test_admin_lists_inspects_and_edits_only_configured_translation_fields(): void
    {
        $asset = $this->asset();
        app(MediaLibrary::class)->process($asset);
        $this->actingAs($this->admin());
        $this->get('/admin/media-assets')->assertOk()->assertSee('sample.png');
        Livewire::test(ManageMediaAssets::class)->assertCanSeeTableRecords([$asset])
            ->callAction(TestAction::make('metadata')->table($asset), data: ['translations' => ['fr' => ['alt' => 'Admin text', 'caption' => null, 'credit' => null], 'ar' => ['alt' => null, 'caption' => null, 'credit' => null]]])->assertHasNoActionErrors();
        $this->assertSame('Admin text', $asset->translations()->where('locale', 'fr')->first()->alt);
        $this->actingAs(User::factory()->create())->get('/admin/media-assets')->assertForbidden();
    }

    public function test_deletion_protection_covers_asset_variant_import_and_publication(): void
    {
        $asset = $this->asset();
        app(MediaLibrary::class)->process($asset);
        $variant = $asset->variants()->first();
        $reference = new MediaReference;
        $reference->forceFill(['media_asset_id' => $asset->id, 'media_variant_id' => $variant->id, 'owner_type' => 'future', 'owner_key' => 'example', 'slot' => 'cover'])->save();
        foreach ([$asset, $variant] as $record) {
            try {
                $record->delete();
                $this->fail('Referenced media deleted.');
            } catch (ValidationException) {
                $this->assertTrue($record->fresh()->exists);
            }
        }
        $reference->delete();
        $admin = $this->admin();
        $library = app(MediaLibrary::class);
        $library->approve($asset, $admin);
        $library->publish($asset, $variant->revision, $admin);
        $library->unpublish($asset, $admin);
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $asset->fresh()));
        $this->expectException(ValidationException::class);
        $variant->fresh()->delete();
    }

    public function test_unreferenced_private_asset_can_be_deleted_but_imported_asset_cannot(): void
    {
        $asset = $this->asset();
        app(MediaLibrary::class)->process($asset);
        app(MediaLibrary::class)->delete($asset, $this->admin());
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('media_variants', 0);
        $this->manifest();
        app(AssetImporter::class)->run($this->root, true);
        $asset = MediaAsset::firstOrFail();
        $this->assertFalse(Gate::forUser($this->admin())->allows('delete', $asset));
        $this->expectException(ValidationException::class);
        $asset->delete();
    }

    public function test_locale_validation_is_configuration_driven(): void
    {
        $asset = $this->asset();
        config(['localization.locales' => [['code' => 'es', 'name' => 'Español', 'direction' => 'ltr']]]);
        $asset->translations()->create(['locale' => 'es', 'alt' => 'Piedra']);
        $this->expectException(ValidationException::class);
        $asset->translations()->create(['locale' => 'fr', 'alt' => 'Disabled']);
    }

    public function test_original_and_variant_key_and_disk_confusion_is_rejected(): void
    {
        $asset = $this->asset();
        app(MediaLibrary::class)->process($asset);
        $variant = $asset->variants()->first();
        foreach (['object_key' => $asset->original_key, 'disk' => 'local_published'] as $field => $value) {
            try {
                $variant->fresh()->forceFill([$field => $value])->save();
                $this->fail('Unsafe media variant accepted.');
            } catch (ValidationException) {
                $this->assertSame('private', $variant->fresh()->state);
            }
        }
        $this->expectException(ValidationException::class);
        $asset->forceFill(['original_disk' => 'r2_published'])->save();
    }

    public function test_published_rows_and_objects_cannot_be_overwritten(): void
    {
        $asset = $this->asset();
        $library = app(MediaLibrary::class);
        $revision = $library->process($asset);
        $admin = $this->admin();
        $library->approve($asset, $admin);
        $library->publish($asset, $revision, $admin);
        $variant = $asset->variants()->first();
        try {
            $variant->forceFill(['width' => 999])->save();
            $this->fail('Published variant mutated.');
        } catch (ValidationException) {
            $this->assertSame(100, $variant->fresh()->width);
        }
        $this->expectException(\RuntimeException::class);
        app(MediaStorage::class)->putImmutable($variant->disk, $variant->object_key, 'changed', 'image/webp');
    }

    public static function badConfiguration(): array
    {
        return [['media.private_disk', 'r2_published'], ['media.published_disk', 'local'], ['media.max_pixels', 0], ['media.profiles.card.edge', 9999], ['media.formats', ['svg']], ['media.private_disk', 'r2_private']];
    }

    #[DataProvider('badConfiguration')]
    public function test_unsafe_media_configuration_fails_closed(string $key, mixed $value): void
    {
        $config = new Repository(config()->all());
        $config->set($key, $value);
        $this->expectException(\InvalidArgumentException::class);
        MediaConfiguration::validate($config);
    }

    public function test_r2_adapter_configuration_without_network_and_clean_public_dto(): void
    {
        $config = new Repository(config()->all());
        MediaConfiguration::validate($config); // Blank credentials allowed locally.
        $this->assertTrue(class_exists(AwsS3V3Adapter::class));
        $config->set('media.private_disk', 'r2_private');
        $config->set('media.published_disk', 'r2_published');
        foreach (['r2_private', 'r2_published'] as $disk) {
            foreach (['key' => 'test-only-key', 'secret' => 'test-only-secret', 'endpoint' => 'https://account.r2.cloudflarestorage.com', 'region' => 'auto', 'bucket' => $disk] as $field => $value) {
                $config->set("filesystems.disks.$disk.$field", $value);
            }
        }
        $config->set('filesystems.disks.r2_published.url', 'https://media.example.invalid');
        MediaConfiguration::validate($config);
        $asset = $this->asset();
        $revision = app(MediaLibrary::class)->process($asset);
        $variant = $asset->variants()->first();
        $variant->forceFill(['state' => 'published', 'disk' => 'r2_published', 'publication_revision' => $revision, 'published_at' => now()])->save();
        config(['filesystems.disks.r2_published.url' => 'https://media.example.invalid']);
        $dto = app(PublishedMedia::class)->variant($variant);
        $this->assertSame('https://media.example.invalid/'.$variant->object_key, $dto['url']);
        $this->assertStringNotContainsString('signature', $dto['url']);
        $config->set('filesystems.disks.r2_private.bucket', 'r2_published');
        $this->expectException(\InvalidArgumentException::class);
        MediaConfiguration::validate($config);
    }

    public function test_jpeg_metadata_is_removed_and_webp_input_is_supported(): void
    {
        $path = $this->fixture();
        $image = imagecreatefrompng($path);
        imagejpeg($image, $this->root.'/products/photo.jpg');
        $jpeg = File::get($this->root.'/products/photo.jpg');
        $metadata = "Exif\0\0PRIVATE-GPS-TEST";
        File::put($this->root.'/products/photo.jpg', substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($metadata) + 2).$metadata.substr($jpeg, 2));
        $asset = app(MediaLibrary::class)->ingest($this->root.'/products/photo.jpg', 'products/photo.jpg');
        app(MediaLibrary::class)->process($asset, ['thumb']);
        $bytes = app(MediaStorage::class)->variantBytes($asset->variants()->first());
        $this->assertStringNotContainsString('PRIVATE-GPS-TEST', $bytes);
        $this->assertStringNotContainsString('Exif', $bytes);
        imagewebp($image, $this->root.'/products/input.webp');
        $this->assertSame('image/webp', app(ImageProcessor::class)->inspect($this->root.'/products/input.webp')['mime']);
    }

    public function test_failed_processing_rolls_back_private_files_and_records(): void
    {
        $asset = $this->asset();
        $before = Storage::disk('local')->allFiles();
        config(['media.formats' => ['webp', 'unsupported']]);
        try {
            app(MediaLibrary::class)->process($asset);
            $this->fail('Unsupported output format accepted.');
        } catch (ValidationException) {
            $this->assertSame($before, Storage::disk('local')->allFiles());
            $this->assertDatabaseCount('media_variants', 0);
            $this->assertSame('failed', $asset->fresh()->processing_state);
        }
    }

    public function test_recovery_can_reuse_an_existing_valid_revision_after_a_failed_attempt(): void
    {
        $asset = $this->asset();
        $library = app(MediaLibrary::class);
        $revision = $library->process($asset);
        config(['media.formats' => ['webp', 'unsupported']]);
        try {
            $library->process($asset);
        } catch (ValidationException) {
            $this->assertSame('failed', $asset->fresh()->processing_state);
        }
        config(['media.formats' => ['webp']]);
        $this->assertSame($revision, $library->process($asset));
        $this->assertSame('ready', $asset->fresh()->processing_state);
        $this->assertDatabaseCount('media_variants', 2);
    }

    public function test_forged_large_dimensions_are_rejected_before_pixel_allocation(): void
    {
        $path = $this->fixture();
        $bytes = File::get($path);
        File::put($path, substr_replace($bytes, pack('NN', 9000, 9000), 16, 8));
        $this->expectException(ValidationException::class);
        app(ImageProcessor::class)->inspect($path);
    }

    public function test_import_symlinks_are_rejected_without_reading_or_importing_the_target(): void
    {
        $target = $this->fixture('outside/fixture.png');
        $link = $this->root.'/products/link';
        if (PHP_OS_FAMILY === 'Windows') {
            File::link(dirname($target), $link); // Laravel creates a directory junction without symlink privileges.
        } elseif (! @symlink(dirname($target), $link)) {
            $this->markTestSkipped('Host does not permit creating symlink fixtures.');
        }
        try {
            $this->assertNotSame(str_replace('\\', '/', $link), str_replace('\\', '/', realpath($link)));
            $this->manifest(source: 'products/link/fixture.png');
            try {
                $report = app(AssetImporter::class)->run($this->root, true);
                $this->assertGreaterThanOrEqual(1, $report['failed']);
                $this->assertNull($report['files'][0]['checksum']);
            } catch (ValidationException $error) {
                $this->assertStringContainsString('not accepted', implode(' ', $error->validator->errors()->all()));
            }
            $this->assertDatabaseCount('media_assets', 0);
        } finally {
            PHP_OS_FAMILY === 'Windows' ? rmdir($link) : unlink($link);
        }
    }

    public function test_missing_duplicate_and_invalid_manifests_and_inventory_limits(): void
    {
        $this->manifest();
        $this->assertSame(1, app(AssetImporter::class)->run($this->root)['failed']);
        $this->fixture();
        $entries = json_decode(File::get($this->root.'/assets-map.json'), true);
        File::put($this->root.'/assets-map.json', json_encode([...$entries, ...$entries]));
        try {
            app(AssetImporter::class)->run($this->root, true);
            $this->fail('Duplicate manifest entry accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('media_assets', 0);
        }
        File::put($this->root.'/assets-map.json', '{ invalid');
        try {
            app(AssetImporter::class)->run($this->root, true);
            $this->fail('Malformed manifest accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('media_assets', 0);
        }
        $this->manifest();
        $this->fixture('products/two.png');
        config(['media.import_max_files' => 1]);
        $this->expectException(ValidationException::class);
        app(AssetImporter::class)->run($this->root);
    }

    public function test_r2_fake_publication_uses_separate_buckets_without_credentials(): void
    {
        foreach (['r2_private', 'r2_published'] as $disk) {
            Storage::set($disk, Storage::build(['driver' => 'local', 'root' => $this->root.'/disks/'.$disk, 'throw' => true]));
        }
        config(['media.private_disk' => 'r2_private', 'media.published_disk' => 'r2_published']);
        $asset = $this->asset();
        $library = app(MediaLibrary::class);
        $revision = $library->process($asset, ['thumb']);
        $admin = $this->admin();
        $library->approve($asset, $admin);
        $library->publish($asset, $revision, $admin);
        Storage::disk('r2_private')->assertExists($asset->original_key);
        Storage::disk('r2_published')->assertMissing($asset->original_key);
        Storage::disk('r2_published')->assertExists($asset->variants()->first()->object_key);
        $this->get('/media/'.$asset->variants()->first()->object_key)->assertNotFound();
    }

    public function test_public_object_writes_set_cache_control_without_public_acl(): void
    {
        $adapter = \Mockery::mock(FilesystemAdapter::class);
        $adapter->shouldReceive('fileExists')->once()->with('images/test')->andReturn(false);
        $adapter->shouldReceive('put')->once()->with('images/test', 'encoded', \Mockery::on(fn ($options) => $options['ContentType'] === 'image/webp' && $options['CacheControl'] === MediaStorage::CACHE_CONTROL && $options['Metadata']['sha256'] === hash('sha256', 'encoded') && $options['params']['IfNoneMatch'] === '*' && ! isset($options['visibility'], $options['ACL'])))->andReturn(true);
        $adapter->shouldReceive('size')->once()->with('images/test')->andReturn(7);
        $adapter->shouldReceive('readStream')->once()->with('images/test')->andReturnUsing(function () {
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, 'encoded');
            rewind($stream);

            return $stream;
        });
        Storage::shouldReceive('disk')->with('r2_published')->andReturn($adapter);
        $this->assertTrue(app(MediaStorage::class)->putImmutable('r2_published', 'images/test', 'encoded', 'image/webp'));
    }

    public function test_local_disk_roots_and_serving_must_remain_private_and_separate(): void
    {
        $config = new Repository(config()->all());
        $config->set('filesystems.disks.local_published.root', $config->get('filesystems.disks.local.root').'/nested');
        try {
            MediaConfiguration::validate($config);
            $this->fail('Nested roots accepted.');
        } catch (\InvalidArgumentException) {
            $this->assertSame([], config('filesystems.links'));
        }
        $config->set('filesystems.disks.local_published.root', storage_path('app/published'));
        $config->set('filesystems.disks.local.serve', true);
        $this->expectException(\InvalidArgumentException::class);
        MediaConfiguration::validate($config);
    }

    public function test_disabled_manager_actions_reject_forged_livewire_calls(): void
    {
        $asset = $this->asset();
        app(MediaLibrary::class)->process($asset);
        $this->actingAs(User::factory()->create(['active' => true]));
        Livewire::test(ManageMediaAssets::class)->assertActionHidden(TestAction::make('approve')->table($asset))->assertActionHidden(TestAction::make('publish')->table($asset))->assertActionHidden(TestAction::make('provenance')->table($asset))->assertActionHidden(TestAction::make('delete')->table($asset));
        $this->expectException(AuthorizationException::class);
        app(MediaLibrary::class)->approve($asset, auth()->user());
    }
}
