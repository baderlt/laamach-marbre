<?php

namespace Tests\Feature;

use App\Media\MediaConfiguration;
use App\Media\MediaStorage;
use App\Media\R2MediaMigration;
use Aws\Exception\AwsException;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ModelFixtures;
use Tests\Support\ProjectFixtures;
use Tests\Support\R2AnonymousS3Diagnostic;
use Tests\TestCase;
use Throwable;

/** Prepared only: six synthetic objects, no content rows, no automatic cleanup. */
class R2LiveStorageTest extends TestCase
{
    public function test_isolated_live_objects_and_cdn_retrieval(): void
    {
        if (getenv('R2_LIVE_TESTS_APPROVED') !== 'yes') {
            $this->markTestSkipped('Live R2 writes require separate explicit approval.');
        }
        $prefix = getenv('R2_LIVE_PREFIX') ?: '';
        $this->assertSame('readiness-tests/2c49dcb1-4004-4f08-9e22-ccd6399e10dd', $prefix);
        $origin = getenv('R2_LIVE_FRONTEND_ORIGIN') ?: '';
        $this->assertContains($origin, ['https://laamachmarbre.com', 'https://www.laamachmarbre.com']);
        $this->assertSame('yes', getenv('R2_PRIVATE_PUBLIC_ACCESS_REVIEWED'), 'Review current Cloudflare dashboard settings for the private bucket before any writes.');
        $root = storage_path('app/media-migration/'.str_replace('/', '-', $prefix));
        File::ensureDirectoryExists($root, 0700, true);
        $manifestPath = $root.'/smoke-manifest.json';
        $this->assertFileDoesNotExist($manifestPath, 'An approved smoke prefix is single-use.');
        $sharedKey = getenv('R2_SMOKE_ACCESS_KEY_ID') ?: null;
        $sharedSecret = getenv('R2_SMOKE_SECRET_ACCESS_KEY') ?: null;
        $singleCredential = $sharedKey !== null || $sharedSecret !== null;
        $manifest = ['prefix' => $prefix, 'credential_mode' => $singleCredential ? 'shared' : 'separate', 'private_public_access_configuration' => 'EXTERNAL_DASHBOARD_REVIEW_ATTESTED', 'cross_bucket_credential_denial' => $singleCredential ? 'NOT TESTED' : 'pending', 'objects' => [], 'cleanup' => 'requires_separate_explicit_approval'];
        $anonymousInconclusive = [];
        $stage = 'configuration';
        try {
            $copy = new Repository(config()->all());
            $copy->set('media.private_disk', 'r2_private');
            $copy->set('media.published_disk', 'r2_published');
            if ($singleCredential) {
                $this->assertNotNull($sharedKey);
                $this->assertNotNull($sharedSecret);
            }
            $configs = [];
            foreach (['private', 'published'] as $role) {
                $env = 'R2_SMOKE_'.strtoupper($role).'_';
                $configs[$role] = array_replace(config('filesystems.disks.r2_migration_'.$role), [
                    'bucket' => 'laamach-'.$role,
                    'key' => $singleCredential ? $sharedKey : (getenv($env.'ACCESS_KEY_ID') ?: null),
                    'secret' => $singleCredential ? $sharedSecret : (getenv($env.'SECRET_ACCESS_KEY') ?: null),
                    'token' => $singleCredential ? (getenv('R2_SMOKE_SESSION_TOKEN') ?: null) : (getenv($env.'SESSION_TOKEN') ?: null),
                ]);
                $this->assertSame('laamach-'.$role, $configs[$role]['bucket']);
                $this->assertSame('https://62b12e721a034df158ec37079d23d610.r2.cloudflarestorage.com', rtrim($configs[$role]['endpoint'] ?? '', '/'));
                $copy->set('filesystems.disks.r2_'.$role, $configs[$role] + ($role === 'published' ? ['url' => 'https://media.laamachmarbre.com'] : []));
            }
            $this->assertSame('https://media.laamachmarbre.com', rtrim($copy->get('filesystems.disks.r2_published.url'), '/'));
            MediaConfiguration::validate($copy);
            foreach ($configs as $role => $config) {
                Storage::set('r2_migration_'.$role, Storage::build($config));
            }
            $stage = 'synthetic-fixtures';
            $image = imagecreatetruecolor(8, 8);
            imagepng($image, $root.'/synthetic.png');
            imagedestroy($image);
            [$json, $binary] = ModelFixtures::document(true);
            File::put($root.'/synthetic.glb', ModelFixtures::pack($json, $binary));
            $video = ProjectFixtures::videoFile($root);
            $files = ['png' => [$root.'/synthetic.png', 'image/png'], 'mp4' => [$video, 'video/mp4'], 'glb' => [$root.'/synthetic.glb', 'model/gltf-binary']];
            foreach (['private', 'published'] as $role) {
                foreach ($files as $format => [$path, $mime]) {
                    $bytes = filesize($path);
                    $this->assertGreaterThan(0, $bytes);
                    $this->assertLessThanOrEqual(1048576, $bytes);
                    $hash = hash_file('sha256', $path);
                    $manifest['objects'][] = ['role' => $role, 'bucket' => 'laamach-'.$role, 'key' => $prefix.'/'.$role.'/'.$hash.'.'.$format, 'sha256' => $hash, 'bytes' => $bytes, 'mime' => $mime, 'format' => $format, 'path' => $path, 'verified' => false];
                }
            }
            $this->manifest($manifestPath, $manifest);
            $cdn = 'https://media.laamachmarbre.com/';
            foreach ($manifest['objects'] as $index => $entry) {
                $stage = $entry['role'].'_'.$entry['format'];
                $disk = 'r2_migration_'.$entry['role'];
                $store = Storage::disk($disk);
                $this->assertFalse($store->fileExists($entry['key']), 'Refusing reuse of an existing cloud key.');
                $this->assertTrue(app(MediaStorage::class)->putImmutableFile($disk, $entry['key'], $entry['path'], $entry['sha256'], $entry['mime']));
                $manifest['objects'][$index]['operation_result'] = 'created_and_byte_verified';
                $manifest['objects'][$index]['authenticated_readback'] = 'SHA256_VERIFIED';
                $this->manifest($manifestPath, $manifest);
                $head = $store->getClient()->headObject(['Bucket' => $entry['bucket'], 'Key' => $entry['key']]);
                $this->assertSame($entry['mime'], $head['ContentType']);
                $this->assertSame($entry['sha256'], $head['Metadata']['sha256']);
                $this->assertSame($entry['role'] === 'published' ? MediaStorage::CACHE_CONTROL : 'private, no-store', $head['CacheControl']);
                $manifest['objects'][$index]['head'] = ['mime' => $head['ContentType'], 'sha256_metadata' => $head['Metadata']['sha256'], 'cache_control' => $head['CacheControl']];
                if ($entry['role'] === 'private') {
                    $cdnStatus = Http::timeout(15)->withoutRedirecting()->head($cdn.$entry['key'])->status();
                    $manifest['objects'][$index]['cdn'] = ['status' => $cdnStatus];
                    $this->manifest($manifestPath, $manifest);
                    $this->assertContains($cdnStatus, [403, 404]);
                    $anonymous = rtrim($configs['private']['endpoint'], '/').'/'.$entry['bucket'].'/'.$entry['key'];
                    $diagnostic = R2AnonymousS3Diagnostic::classify(Http::timeout(15)->withoutRedirecting()->get($anonymous));
                    $manifest['objects'][$index]['anonymous_s3_diagnostic'] = $diagnostic;
                    $this->manifest($manifestPath, $manifest);
                    $this->assertNotSame('EXPOSED_OR_REDIRECT', $diagnostic['verdict'], 'Private object was publicly readable or redirected from the S3 endpoint.');
                    if ($diagnostic['verdict'] === 'INCONCLUSIVE') {
                        $anonymousInconclusive[] = $entry['format'];
                    }
                } else {
                    $request = Http::timeout(15)->withoutRedirecting()->withHeaders(['Origin' => $origin, 'Accept-Encoding' => 'identity']);
                    $first = $request->get($cdn.$entry['key']);
                    $second = $request->get($cdn.$entry['key']);
                    foreach ([$first, $second] as $response) {
                        $this->assertSame(200, $response->status());
                        $this->assertSame($entry['sha256'], hash('sha256', $response->body()));
                        $this->assertSame($entry['mime'], $response->header('Content-Type'));
                        $this->assertStringContainsString('immutable', $response->header('Cache-Control'));
                        $this->assertSame($origin, $response->header('Access-Control-Allow-Origin'));
                        $exposed = array_map('strtolower', array_map('trim', explode(',', $response->header('Access-Control-Expose-Headers') ?? '')));
                        foreach (['Content-Range', 'Accept-Ranges', 'Content-Length', 'ETag', 'CF-Cache-Status', 'Age'] as $header) {
                            $this->assertContains(strtolower($header), $exposed, 'Published CORS must expose '.$header.'.');
                        }
                    }
                    $manifest['objects'][$index]['cache'] = ['first' => $first->header('CF-Cache-Status'), 'second' => $second->header('CF-Cache-Status'), 'age' => $second->header('Age')];
                    $manifest['objects'][$index]['cdn'] = ['first_status' => $first->status(), 'second_status' => $second->status(), 'cors_origin' => $second->header('Access-Control-Allow-Origin'), 'cors_exposed_headers' => $second->header('Access-Control-Expose-Headers')];
                    $this->manifest($manifestPath, $manifest);
                    $this->assertSame('HIT', $second->header('CF-Cache-Status'), 'Review the approved test-prefix cache rule.');
                    if ($entry['format'] === 'mp4') {
                        $last = min(1023, $entry['bytes'] - 1);
                        $range = $request->replaceHeaders(['Range' => 'bytes=0-'.$last])->get($cdn.$entry['key']);
                        $this->assertSame(206, $range->status());
                        $this->assertSame('bytes 0-'.$last.'/'.$entry['bytes'], $range->header('Content-Range'));
                        $this->assertSame($last + 1, strlen($range->body()));
                        $unsatisfiableStatus = $request->replaceHeaders(['Range' => 'bytes='.$entry['bytes'].'-'])->get($cdn.$entry['key'])->status();
                        $manifest['objects'][$index]['range'] = ['status' => $range->status(), 'content_range' => $range->header('Content-Range'), 'received_bytes' => strlen($range->body()), 'unsatisfiable_status' => $unsatisfiableStatus];
                        $this->manifest($manifestPath, $manifest);
                        $this->assertSame(416, $unsatisfiableStatus);
                    }
                }
                $manifest['objects'][$index]['verified'] = true;
                $this->manifest($manifestPath, $manifest);
            }
            $stage = 'bucket-token-isolation';
            foreach ($singleCredential ? [] : ['private', 'published'] as $role) {
                $other = collect($manifest['objects'])->firstWhere('role', $role === 'private' ? 'published' : 'private');
                try {
                    Storage::disk('r2_migration_'.$role)->getClient()->headObject(['Bucket' => $other['bucket'], 'Key' => $other['key']]);
                    $this->fail('A smoke token can access the other bucket.');
                } catch (AwsException $error) {
                    $this->assertSame(403, $error->getStatusCode());
                }
            }
            if (! $singleCredential) {
                $manifest['cross_bucket_credential_denial'] = 'verified';
                $this->manifest($manifestPath, $manifest);
            }
        } catch (Throwable $error) {
            $manifest['stopped_at'] = $stage;
            $this->manifest($manifestPath, $manifest);
            $this->fail('Isolated smoke stopped at '.$stage.'. '.R2MediaMigration::safeFailure($error));
        }
        if ($anonymousInconclusive !== []) {
            $this->markTestIncomplete('Unsigned S3 diagnostic inconclusive for private '.implode(', ', $anonymousInconclusive).'; dashboard access review and CDN checks remain separate.');
        }
        // All six objects are deliberately retained, including after failed assertions.
        // Rendering in a deployed browser and real-device behavior remain separate checks.
    }

    private function manifest(string $path, array $manifest): void
    {
        File::replace($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), 0600);
    }
}
