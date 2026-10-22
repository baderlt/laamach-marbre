<?php

namespace App\Media;

use Aws\Exception\AwsException;
use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class R2MediaMigration
{
    public function configured(): void
    {
        $copy = new Repository(config()->all());
        $copy->set('media.private_disk', 'r2_private');
        $copy->set('media.published_disk', 'r2_published');
        // Committed assets must remain readable by the normal application on partial completion.
        MediaConfiguration::validate($copy);
        foreach (['private', 'published'] as $role) {
            foreach (['key', 'secret'] as $field) {
                $copy->set("filesystems.disks.r2_$role.$field", config("filesystems.disks.r2_migration_$role.$field"));
            }
        }
        MediaConfiguration::validate($copy);
        $expiry = config('media.migration_expires_at');
        if ($expiry && CarbonImmutable::parse($expiry)->isPast()) {
            throw new RuntimeException('Migration credentials have expired; configure a fresh scoped token privately.');
        }
    }

    public function apply(array $plan, string $planHash, string $directory, string $backup, string $backupHash): array
    {
        $this->configured();
        if (($plan['version'] ?? null) !== 1 || ($plan['summary']['blocking_problems'] ?? 1) !== 0 || $plan['database'] !== DB::connection()->getDatabaseName()) {
            throw new RuntimeException('The approved plan is blocked or belongs to another database.');
        }
        if (! is_file($backup) || is_link($backup) || filesize($backup) < 1 || ! preg_match('/^[a-f0-9]{64}$/D', $backupHash) || ! hash_equals($backupHash, hash_file('sha256', $backup))) {
            throw new RuntimeException('A checksum-verified, consistent database backup is required.');
        }
        if (! hash_equals($plan['content_fingerprint'], app(MediaMigrationInventory::class)->fingerprint())) {
            throw new RuntimeException('Content changed since the dry-run. Generate and review a new plan.');
        }
        File::ensureDirectoryExists($directory, 0700, true);
        $journalPath = $directory.'/journal.json';
        $journal = is_file($journalPath) ? json_decode(file_get_contents($journalPath), true, 512, JSON_THROW_ON_ERROR) : ['plan_sha256' => $planHash, 'backup_sha256' => $backupHash, 'assets' => []];
        if ($journal['plan_sha256'] !== $planHash || $journal['backup_sha256'] !== $backupHash) {
            throw new RuntimeException('Use the same approved plan and backup when resuming.');
        }
        foreach (collect($plan['objects'])->where('eligible', true)->groupBy('asset_id') as $assetId => $entries) {
            try {
                // Reverify even completed assets on retry. No content processing or publication occurs here.
                DB::transaction(function () use ($assetId, $entries) {
                    $asset = DB::table('media_assets')->where('id', $assetId)->lockForUpdate()->first();
                    if (! $asset || ! $asset->client_supplied) {
                        throw new RuntimeException('Client provenance changed.');
                    }
                    foreach ($entries as $entry) {
                        $this->assertRecord($entry);
                        if ($entry['destination'] === 'r2_published' && ($entry['state'] !== 'published' || $asset->approval_state !== 'approved')) {
                            throw new RuntimeException('Private or unapproved content cannot enter the published bucket.');
                        }
                        $role = $entry['destination'] === 'r2_published' ? 'published' : 'private';
                        $target = 'r2_migration_'.$role;
                        if (config("filesystems.disks.$target.bucket") !== $entry['bucket']) {
                            throw new RuntimeException('Destination bucket differs from the approved plan.');
                        }
                        $storage = app(MediaStorage::class);
                        if (! Storage::disk($target)->fileExists($entry['key'])) {
                            $path = $storage->temporaryCopy($entry['source_disk'], $entry['key'], $entry['sha256'], max(config('media.max_bytes'), config('video.max_bytes')));
                            try {
                                $storage->putImmutableFile($target, $entry['key'], $path, $entry['sha256'], $entry['mime']);
                            } finally {
                                unlink($path);
                            }
                        } else {
                            $storage->verifyStored($target, $entry['key'], $entry['sha256'], $entry['bytes']);
                        }
                        $store = Storage::disk($target);
                        if (method_exists($store, 'getClient')) {
                            $head = $store->getClient()->headObject(['Bucket' => $entry['bucket'], 'Key' => $entry['key']]);
                            if (($head['ContentType'] ?? null) !== $entry['mime'] || ($role === 'published' && ($head['CacheControl'] ?? null) !== MediaStorage::CACHE_CONTROL) || (isset($head['Metadata']['sha256']) && $head['Metadata']['sha256'] !== $entry['sha256'])) {
                                throw new RuntimeException('Destination integrity or delivery metadata differs; refusing an immutable overwrite.');
                            }
                        }
                    }
                    foreach ($entries as $entry) {
                        if ($entry['kind'] === 'working_copy') {
                            continue;
                        }
                        $table = $entry['kind'] === 'original' ? 'media_assets' : 'media_variants';
                        $field = $entry['kind'] === 'original' ? 'original_disk' : 'disk';
                        // Dedicated relocation exception to model immutability: ONLY the disk changes.
                        // All identities, states, revisions, keys, timestamps and content references remain intact.
                        DB::table($table)->where('id', $entry['id'])->where($field, $entry['source_disk'])->update([$field => $entry['destination']]);
                    }
                });
                $journal['assets'][$assetId] = ['status' => 'verified_and_relocated', 'objects' => count($entries)];
            } catch (Throwable $error) {
                $journal['assets'][$assetId] = ['status' => 'failed_references_unchanged', 'reason' => self::safeFailure($error)];
                File::replace($journalPath, json_encode($journal, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), 0600);
                throw new RuntimeException(self::safeFailure($error));
            }
            File::replace($journalPath, json_encode($journal, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), 0600);
        }
        if (! hash_equals($plan['content_fingerprint'], app(MediaMigrationInventory::class)->fingerprint())) {
            throw new RuntimeException('Post-migration content fingerprint changed; stop and inspect the backup.');
        }

        return $journal;
    }

    private function assertRecord(array $entry): void
    {
        if (! in_array($entry['destination'], ['r2_private', 'r2_published'], true) || ! in_array($entry['source_disk'], ['local', 'local_published', 'r2_private', 'r2_published'], true)) {
            throw new RuntimeException('Invalid migration disk role.');
        }
        $table = $entry['kind'] === 'original' ? 'media_assets' : 'media_variants';
        $field = $entry['kind'] === 'original' ? 'original_disk' : 'disk';
        $current = (array) DB::table($table)->where('id', $entry['id'])->lockForUpdate()->first();
        $expected = $entry['record'];
        $relocated = $entry['kind'] === 'working_copy' ? 'r2_published' : $entry['destination'];
        if (! in_array($current[$field] ?? null, [$entry['record'][$field], $relocated], true)) {
            throw new RuntimeException('Storage reference changed since inventory.');
        }
        $original = $entry['kind'] === 'original';
        $state = $original || $entry['kind'] === 'working_copy' ? 'private' : ($current['state'] ?? null);
        if (($original ? ($current['id'] ?? null) : ($current['media_asset_id'] ?? null)) !== $entry['asset_id'] || ($current[$original ? 'original_key' : 'object_key'] ?? null) !== $entry['key'] || ($current['checksum'] ?? null) !== $entry['sha256'] || ($current['bytes'] ?? null) !== $entry['bytes'] || $state !== $entry['state'] || $entry['destination'] !== ($state === 'published' ? 'r2_published' : 'r2_private')) {
            throw new RuntimeException('Plan identity or storage role does not match the media record.');
        }
        unset($current[$field], $expected[$field]);
        ksort($current);
        ksort($expected);
        if ($current !== $expected) {
            throw new RuntimeException('Media metadata changed since inventory.');
        }
        if ($entry['kind'] === 'original') {
            MediaPath::assertOriginal($entry['key'], $entry['asset_id'], $entry['sha256']);
        } else {
            MediaPath::assertVariant($entry['key'], $entry['asset_id'], $entry['record']['profile_version'], $entry['sha256'], $entry['record']['format']);
        }
    }

    public static function safeFailure(Throwable $error): string
    {
        for ($cause = $error; $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof AwsException) {
                $code = $cause->getAwsErrorCode();

                return in_array($code, ['ExpiredToken', 'InvalidToken', 'InvalidAccessKeyId', 'SignatureDoesNotMatch', 'AccessDenied', 'NoSuchBucket', 'PreconditionFailed', 'SlowDown'], true) ? 'R2 operation stopped: '.$code.'. Check scoped credentials/expiry, bucket permissions or immutable conflicts privately.' : 'R2 operation stopped. Check credentials, permissions and connectivity privately.';
            }
        }
        if ($error instanceof RuntimeException && in_array($error->getMessage(), ['Migration credentials have expired; configure a fresh scoped token privately.', 'A checksum-verified, consistent database backup is required.', 'Content changed since the dry-run. Generate and review a new plan.', 'A reviewed, checksum-approved plan is required.', 'Choose a new report directory; existing reviewed plans are immutable.', 'Reports must be outside all public web directories.', 'Destination bucket differs from the approved plan.', 'Use the same approved plan and backup when resuming.'], true)) {
            return $error->getMessage();
        }

        return 'Migration stopped: source integrity, content snapshot, storage availability or destination validation failed. Existing local files are retained.';
    }
}
