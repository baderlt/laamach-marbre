<?php

namespace Tests\Feature;

use App\Filament\Resources\MediaAssetResource\ManageMediaAssets;
use App\Media\AssetImporter;
use App\Media\MediaLibrary;
use App\Media\PublishedMedia;
use App\Media\VideoProcessor;
use App\Models\MediaAsset;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\ProjectFixtures as F;
use Tests\TestCase;

class VideoTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $this->root = storage_path('framework/testing/'.getmypid().'/video/'.Str::uuid());
        File::ensureDirectoryExists($this->root);
        foreach (['local', 'local_published'] as $disk) {
            Storage::set($disk, Storage::build(['driver' => 'local', 'root' => $this->root.'/'.$disk, 'throw' => true]));
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_real_supported_video_is_inspected_remuxed_private_then_explicitly_published(): void
    {
        $library = app(MediaLibrary::class);
        $asset = $library->ingest(F::videoFile($this->root), 'projects/renamed.bin', true);
        $this->assertSame('video', $asset->type);
        $this->assertSame('video/mp4', $asset->mime);
        $this->assertSame(160, $asset->width);
        $this->assertSame(90, $asset->height);
        $this->assertSame($asset->id, $library->ingest(F::videoFile($this->root), 'projects/again.mp4')->id);
        $revision = $library->process($asset);
        $variant = $asset->variants()->firstOrFail();
        $this->assertTrue($variant->metadata['full_decode_validated']);
        $this->assertSame([], app(PublishedMedia::class)->forAsset($asset->fresh()));
        $this->assertSame([], Storage::disk('local_published')->allFiles());
        $private = Storage::disk('local')->get($variant->object_key);
        $this->assertStringNotContainsString('Synthetic test only', $private);
        $this->assertStringNotContainsString('Synthetic private stream name', $private);
        $this->assertLessThan(strpos($private, 'mdat'), strpos($private, 'moov'));
        $library->approve($asset, $this->admin);
        $library->publish($asset, $revision, $this->admin);
        $dto = app(PublishedMedia::class)->forAsset($asset->fresh());
        $this->assertCount(1, $dto);
        $this->assertSame('mp4', $dto[0]['format']);
        $this->assertMatchesRegularExpression('~^videos/[0-9a-f-]{36}/video-v1-[a-f0-9]{16}/[a-f0-9]{64}\.mp4$~', $variant->object_key);
        $this->assertSame(hash('sha256', $private), hash_file('sha256', Storage::disk('local_published')->path($variant->object_key)));
        $this->assertSame($revision, $library->process($asset));
        $this->assertDatabaseCount('media_variants', 1);
        $this->get('/media/'.$asset->original_key)->assertNotFound();
        $this->get('/media/'.$variant->object_key)->assertOk()->assertHeader('Content-Type', 'video/mp4')->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->withHeaders(['Range' => 'bytes=0-63'])->get('/media/'.$variant->object_key)->assertStatus(206)->assertHeader('Content-Length', '64');
        $this->expectException(AuthorizationException::class);
        $library->delete($asset, $this->admin);
    }

    public function test_published_video_new_version_uses_new_key_and_existing_url_stays_immutable(): void
    {
        $asset = F::video($this->admin, $this->root);
        $first = app(PublishedMedia::class)->forAsset($asset)[0];
        config(['video.version' => 'v2']);
        $revision = app(MediaLibrary::class)->process($asset);
        app(MediaLibrary::class)->publish($asset, $revision, $this->admin);
        $second = app(PublishedMedia::class)->forAsset($asset->fresh())[0];
        $this->assertNotSame($first['url'], $second['url']);
        $this->get(parse_url($first['url'], PHP_URL_PATH))->assertOk();
        app(MediaLibrary::class)->unpublish($asset, $this->admin);
        $this->assertSame([], app(PublishedMedia::class)->forAsset($asset->fresh()));
        $this->get(parse_url($second['url'], PHP_URL_PATH))->assertOk();
    }

    public function test_invalid_container_mime_corruption_oversize_and_path_traversal_reject(): void
    {
        $valid = F::videoFile($this->root);
        $fake = $this->root.'/fake.mp4';
        file_put_contents($fake, str_repeat('not video', 20));
        $truncated = $this->root.'/truncated.mp4';
        file_put_contents($truncated, substr(file_get_contents($valid), 0, 80));
        foreach ([$fake, $truncated] as $path) {
            try {
                app(MediaLibrary::class)->ingest($path, 'projects/file.mp4');
                $this->fail('Malformed video accepted.');
            } catch (\Throwable $error) {
                $this->assertInstanceOf(ValidationException::class, $error);
            }
        }
        config(['video.max_bytes' => 32]);
        try {
            app(VideoProcessor::class)->inspect($valid);
            $this->fail('Oversize accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('media_assets', 0);
        }
        config(['video.max_bytes' => 52428800]);
        $this->expectException(ValidationException::class);
        app(MediaLibrary::class)->ingest($valid, '../unsafe.mp4');
    }

    public function test_supported_codec_duration_dimensions_and_missing_tools_are_enforced(): void
    {
        $path = F::videoFile($this->root);
        foreach (['video.max_duration' => 1, 'video.max_dimension' => 100, 'video.max_fps' => 5, 'video.ffprobe' => $this->root.'/missing-ffprobe.exe'] as $key => $value) {
            $old = config($key);
            config([$key => $value]);
            try {
                app(VideoProcessor::class)->inspect($path);
                $this->fail('Unsupported bound accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('media_assets', 0);
            } finally {
                config([$key => $old]);
            }
        }
        $unsupported = $this->root.'/unsupported.mp4';
        app(VideoProcessor::class)->run([config('video.ffmpeg'), '-v', 'error', '-i', $path, '-c:v', 'mpeg4', '-y', $unsupported]);
        $this->expectException(ValidationException::class);
        app(VideoProcessor::class)->inspect($unsupported);
    }

    public function test_upload_is_authenticated_and_filament_stages_real_video_without_approval(): void
    {
        $this->post(route('livewire.upload-file', [], false), [], ['Accept' => 'application/json'])->assertUnauthorized();
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('uploadVideo', MediaAsset::class));
        $this->actingAs($this->admin);
        $file = UploadedFile::fake()->createWithContent('synthetic-video.mp4', file_get_contents(F::videoFile($this->root)));
        $file->mimeTypeToReport = 'video/mp4';
        Livewire::test(ManageMediaAssets::class)->callAction(TestAction::make('uploadVideo')->table(), data: ['video' => $file, 'client_supplied' => true])->assertHasNoActionErrors();
        $asset = MediaAsset::firstOrFail();
        $this->assertSame('video', $asset->type);
        $this->assertSame('ready', $asset->processing_state);
        $this->assertSame('pending', $asset->approval_state);
        $this->assertNull($asset->active_revision);
        $this->assertSame([], Storage::disk('local_published')->allFiles());
        $this->get(route('media.preview', $asset, false))->assertNotFound();
    }

    public function test_aac_audio_is_validated_and_remuxed_without_transcoding(): void
    {
        $path = $this->root.'/with-audio.mp4';
        app(VideoProcessor::class)->run([config('video.ffmpeg'), '-nostdin', '-v', 'error', '-i', F::videoFile($this->root), '-f', 'lavfi', '-i', 'sine=frequency=440:sample_rate=48000:duration=2', '-c:v', 'copy', '-c:a', 'aac', '-ac', '1', '-shortest', '-y', $path]);
        $info = app(VideoProcessor::class)->inspect($path);
        $runtime = app(VideoProcessor::class)->runtime($path, $this->root.'/runtime.mp4');
        $this->assertSame('video/mp4', $runtime['mime']);
        $this->assertSame($info['width'], $runtime['width']);
        $this->assertLessThanOrEqual(0.1, abs($info['duration'] - $runtime['duration']));
    }

    public function test_importer_requires_explicit_video_mapping_and_stages_only_private_media(): void
    {
        File::ensureDirectoryExists($this->root.'/projects');
        copy(F::videoFile($this->root), $this->root.'/projects/synthetic.mp4');
        $importer = app(AssetImporter::class);
        $this->assertSame(1, $importer->run($this->root)['skipped']);
        file_put_contents($this->root.'/assets-map.json', json_encode([['source' => 'projects/synthetic.mp4', 'target_type' => 'project', 'target_key' => 'explicit-future-key', 'role' => 'project_video', 'order' => 0]]));
        $this->assertSame(1, $importer->run($this->root)['accepted']);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame(1, $importer->run($this->root, true, true)['accepted']);
        $this->assertDatabaseHas('media_assets', ['type' => 'video', 'approval_state' => 'pending', 'active_revision' => null]);
        $this->assertDatabaseCount('projects', 0);
        $this->assertSame([], Storage::disk('local_published')->allFiles());
    }
}
