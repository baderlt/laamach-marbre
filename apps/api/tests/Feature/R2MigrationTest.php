<?php

namespace Tests\Feature;

use App\Media\MediaLibrary;
use App\Media\MediaMigrationInventory;
use App\Media\MediaStorage;
use App\Media\PublishedMedia;
use App\Media\R2MediaMigration;
use App\Models\MediaAsset;
use App\Models\User;
use Aws\Command;
use Aws\Exception\AwsException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class R2MigrationTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/'.getmypid().'/r2-'.Str::uuid());
        File::ensureDirectoryExists($this->root);
        $this->admin = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        foreach (['local', 'local_published', 'r2_migration_private', 'r2_migration_published'] as $disk) {
            Storage::set($disk, Storage::build(['driver' => 'local', 'root' => $this->root.'/'.$disk, 'throw' => true]));
        }
        foreach (['private', 'published'] as $role) {
            foreach (['r2_'.$role, 'r2_migration_'.$role] as $disk) {
                config(["filesystems.disks.$disk" => ['driver' => 's3', 'key' => 'isolated-test-key', 'secret' => 'isolated-test-secret', 'bucket' => 'laamach-'.$role, 'endpoint' => 'https://account.r2.cloudflarestorage.com', 'region' => 'auto', ...($role === 'published' ? ['url' => 'https://media.laamachmarbre.com'] : [])]]);
            }
        }
        Storage::set('r2_private', Storage::disk('r2_migration_private'));
        Storage::set('r2_published', Storage::disk('r2_migration_published'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function image(bool $publish = true, string $source = 'products/client-reference.png'): MediaAsset
    {
        $path = $this->root.'/source.png';
        $image = imagecreatetruecolor(40, 30);
        imagefill($image, 0, 0, imagecolorallocate($image, 20 + MediaAsset::count() * 20, 100, 130));
        imagepng($image, $path);
        imagedestroy($image);
        $library = app(MediaLibrary::class);
        $asset = $library->ingest($path, $source, true);
        $revision = $library->process($asset);
        if ($publish) {
            $library->approve($asset, $this->admin);
            $library->publish($asset, $revision, $this->admin);
        }

        return $asset->fresh();
    }

    private function migrate(array $plan): array
    {
        $backup = $this->root.'/isolated-test-backup.sql';
        File::put($backup, '-- QA-only simulated backup; no production backup claim');

        return app(R2MediaMigration::class)->apply($plan, hash('sha256', json_encode($plan)), $this->root.'/journal', $backup, hash_file('sha256', $backup));
    }

    public function test_relocation_requires_independently_configured_runtime_and_migration_credentials(): void
    {
        foreach (['r2_private', 'r2_published', 'r2_migration_private', 'r2_migration_published'] as $disk) {
            $key = config("filesystems.disks.$disk.key");
            config(["filesystems.disks.$disk.key" => null]);
            try {
                app(R2MediaMigration::class)->configured();
                $this->fail('Missing independent credentials were accepted.');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            } finally {
                config(["filesystems.disks.$disk.key" => $key]);
            }
        }
    }

    public function test_dry_run_hashes_all_managed_copies_without_writes_and_excludes_fixtures(): void
    {
        $asset = $this->image();
        $this->image(false, 'products/synthetic-fixture.png');
        $before = app(MediaMigrationInventory::class)->fingerprint();
        $files = Storage::disk('local')->allFiles();
        $plan = app(MediaMigrationInventory::class)->run();
        $this->assertSame(0, $plan['summary']['blocking_problems']);
        $this->assertSame(5, $plan['summary']['eligible_objects']);
        $this->assertNotEmpty($plan['duplicate_content']);
        $this->assertSame($before, app(MediaMigrationInventory::class)->fingerprint());
        $this->assertSame($files, Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('r2_migration_private')->allFiles());
        $this->assertSame([], Storage::disk('r2_migration_published')->allFiles());
        $this->assertSame('local', $asset->fresh()->original_disk);
    }

    public function test_migration_is_verified_idempotent_and_preserves_identity_states_and_local_files(): void
    {
        $asset = $this->image();
        $plan = app(MediaMigrationInventory::class)->run();
        $this->migrate($plan);
        $first = Storage::disk('r2_migration_published')->allFiles();
        $this->migrate($plan);
        $this->assertSame($first, Storage::disk('r2_migration_published')->allFiles());
        $this->assertSame($plan['content_fingerprint'], app(MediaMigrationInventory::class)->fingerprint());
        $this->assertSame('r2_private', $asset->fresh()->original_disk);
        Storage::disk('local')->assertExists($asset->original_key);
        $this->assertSame($asset->checksum, hash('sha256', app(MediaStorage::class)->originalBytes($asset->fresh())));
        foreach ($asset->variants()->get() as $variant) {
            $this->assertSame('r2_published', $variant->disk);
            $this->assertSame('published', $variant->state);
            $this->assertStringStartsWith('https://media.laamachmarbre.com/', app(PublishedMedia::class)->variant($variant)['url']);
            Storage::disk('local_published')->assertExists($variant->object_key);
        }
    }

    public function test_missing_or_corrupt_sources_block_the_inventory(): void
    {
        $asset = $this->image();
        Storage::disk('local')->delete($asset->original_key);
        $plan = app(MediaMigrationInventory::class)->run();
        $this->assertGreaterThan(0, $plan['summary']['blocking_problems']);
        $this->expectException(RuntimeException::class);
        $this->migrate($plan);
    }

    public function test_interrupted_copy_or_hash_conflict_leaves_references_usable_and_retry_recovers(): void
    {
        $asset = $this->image();
        $variant = $asset->variants()->first();
        $plan = app(MediaMigrationInventory::class)->run();
        Storage::disk('r2_migration_published')->put($variant->object_key, 'wrong bytes');
        try {
            $this->migrate($plan);
            $this->fail('Corrupt immutable destination accepted.');
        } catch (RuntimeException) {
            $this->assertSame('local', $asset->fresh()->original_disk);
        }
        $this->assertSame(['local_published'], $asset->variants()->pluck('disk')->unique()->all());
        Storage::disk('r2_migration_published')->delete($variant->object_key); // QA fake root only.
        $this->migrate($plan);
        $this->assertSame('r2_private', $asset->fresh()->original_disk);
    }

    public function test_pending_assets_never_enter_the_published_bucket(): void
    {
        $asset = $this->image(false);
        $plan = app(MediaMigrationInventory::class)->run();
        $this->migrate($plan);
        $this->assertSame('pending', $asset->fresh()->approval_state);
        $this->assertNull($asset->fresh()->active_revision);
        $this->assertSame([], Storage::disk('r2_migration_published')->allFiles());
        $this->assertSame([], app(PublishedMedia::class)->forAsset($asset->fresh()));
    }

    public function test_changed_content_expired_credentials_and_forged_destinations_are_rejected(): void
    {
        $asset = $this->image(false);
        $plan = app(MediaMigrationInventory::class)->run();
        $plan['objects'][0]['destination'] = 'r2_published';
        try {
            $this->migrate($plan);
            $this->fail('Private publication bypass accepted.');
        } catch (RuntimeException) {
        }
        $this->assertSame('local', $asset->fresh()->original_disk);
        config(['media.migration_expires_at' => '2000-01-01T00:00:00Z']);
        try {
            $this->migrate($plan);
            $this->fail('Expired token accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('expired', $error->getMessage());
        }
        config(['media.migration_expires_at' => null]);
        $plan = app(MediaMigrationInventory::class)->run();
        DB::table('media_assets')->where('id', $asset->id)->update(['processing_state' => 'failed']);
        $this->expectException(RuntimeException::class);
        $this->migrate($plan);
    }

    public function test_sdk_credential_permission_and_expiry_errors_are_sanitized(): void
    {
        foreach (['InvalidAccessKeyId', 'ExpiredToken', 'AccessDenied'] as $code) {
            $error = new AwsException('SECRET-CREDENTIAL signed request must never appear', new Command('PutObject'), ['code' => $code]);
            $message = R2MediaMigration::safeFailure($error);
            $this->assertStringContainsString($code, $message);
            $this->assertStringNotContainsString('SECRET-CREDENTIAL', $message);
        }
    }

    public function test_future_r2_workflow_uses_private_intake_and_approved_public_variants(): void
    {
        config(['media.private_disk' => 'r2_private', 'media.published_disk' => 'r2_published']);
        $asset = $this->image(false);
        $this->assertSame('r2_private', $asset->original_disk);
        $this->assertSame([], Storage::disk('r2_published')->allFiles());
        $library = app(MediaLibrary::class);
        $editor = User::factory()->create(['active' => true, 'role' => 'content_manager']);
        try {
            $library->approve($asset, $editor);
            $this->fail('Editor approved publication.');
        } catch (AuthorizationException) {
        }
        $library->approve($asset, $this->admin);
        $library->publish($asset, $asset->variants()->first()->revision, $this->admin);
        $this->assertNotEmpty(Storage::disk('r2_published')->allFiles());
        $this->assertStringStartsWith('https://media.laamachmarbre.com/', app(PublishedMedia::class)->forAsset($asset->fresh())[0]['url']);
    }
}
