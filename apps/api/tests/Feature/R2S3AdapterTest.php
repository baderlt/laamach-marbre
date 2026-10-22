<?php

namespace Tests\Feature;

use App\Media\MediaStorage;
use Aws\Command;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class R2S3AdapterTest extends TestCase
{
    public function test_supported_s3_adapter_preserves_atomic_write_conditions_mime_cache_and_hash_metadata(): void
    {
        foreach (['r2_private', 'r2_published'] as $disk) {
            $this->assertWriteContract($disk, false);
            $this->assertWriteContract($disk, true);
        }
    }

    private function assertWriteContract(string $disk, bool $streamed): void
    {
        $bytes = 'isolated SDK contract payload';
        $put = null;
        $stored = false;
        $handler = function ($command) use (&$put, &$stored, $bytes) {
            if ($command->getName() === 'HeadObject' && ! $stored) {
                return Create::rejectionFor(new S3Exception('not found', new Command('HeadObject'), ['response' => new Response(404), 'code' => 'NotFound']));
            }
            if ($command->getName() === 'PutObject') {
                $put = $command->toArray();
                $stored = true;
            }
            $data = match ($command->getName()) {
                'HeadObject' => ['ContentLength' => strlen($bytes)],
                'GetObject' => ['Body' => Utils::streamFor($bytes), 'ContentLength' => strlen($bytes)],
                'ListObjectsV2' => [],
                'PutObject' => ['ETag' => 'test-etag'],
                default => throw new \RuntimeException('Unexpected SDK command'),
            };

            return Create::promiseFor(new Result($data));
        };
        $config = config('filesystems.disks.'.$disk);
        $config = array_replace($config, ['key' => 'test-only-key', 'secret' => 'test-only-secret', 'bucket' => 'isolated-test', 'endpoint' => 'https://account.r2.cloudflarestorage.com', 'region' => 'auto', 'handler' => $handler, 'retries' => 0]);
        Storage::set($disk, Storage::build($config));
        if ($streamed) {
            $path = tempnam(sys_get_temp_dir(), 'r2-contract-');
            file_put_contents($path, $bytes);
            try {
                $this->assertTrue(app(MediaStorage::class)->putImmutableFile($disk, 'contract-test', $path, hash('sha256', $bytes), 'image/webp'));
            } finally {
                unlink($path);
            }
        } else {
            $this->assertTrue(app(MediaStorage::class)->putImmutable($disk, 'contract-test', $bytes, 'image/webp'));
        }
        $this->assertSame('*', $put['IfNoneMatch']);
        $this->assertSame('image/webp', $put['ContentType']);
        $this->assertSame(hash('sha256', $bytes), $put['Metadata']['sha256']);
        $this->assertSame($disk === 'r2_published' ? MediaStorage::CACHE_CONTROL : 'private, no-store', $put['CacheControl']);
    }
}
