<?php

namespace App\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaMigrationInventory
{
    public const TABLES = ['media_assets', 'media_variants', 'media_references', 'media_translations', 'media_imports', 'products', 'product_translations', 'product_categories', 'product_category_translations', 'product_sizes', 'product_size_translations', 'product_units', 'product_finishes', 'finishes', 'finish_translations', 'projects', 'project_translations', 'project_categories', 'project_category_translations', 'project_category_project', 'project_gallery_items', 'project_products', 'site_contents'];

    public function fingerprint(): string
    {
        $context = hash_init('sha256');
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $rows = DB::table($table)->get()->map(function ($row) use ($table) {
                $row = (array) $row;
                if ($table === 'media_assets') {
                    unset($row['original_disk']);
                }
                if ($table === 'media_variants') {
                    unset($row['disk']);
                }
                ksort($row);

                return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
            })->sort()->values()->all();
            hash_update($context, $table.json_encode($rows, JSON_THROW_ON_ERROR));
        }

        return hash_final($context);
    }

    public function run(): array
    {
        if (MediaAsset::count() > 5000) {
            throw new RuntimeException('Split the inventory before exceeding 5000 assets.');
        }

        return DB::transaction(function () {
            $assets = MediaAsset::with(['variants', 'references', 'imports'])->orderBy('id')->get();
            $objects = $problems = $extra = $known = [];
            $assetIds = $assets->keyBy('id');
            $associations = [];
            $owners = [];
            foreach ($assets->flatMap(fn ($asset) => $asset->references)->groupBy('owner_type') as $type => $references) {
                $table = $type === 'product_viewers' ? 'products' : $type;
                $owners[$type] = in_array($table, self::TABLES, true) && Schema::hasTable($table) ? DB::table($table)->whereIn($table === 'site_contents' ? 'key' : 'id', $references->pluck('owner_key'))->pluck($table === 'site_contents' ? 'key' : 'id')->flip()->all() : [];
            }
            foreach ($assets as $asset) {
                $associations[$asset->id] = $asset->references->map(fn ($ref) => ['owner' => $ref->owner_type, 'key' => $ref->owner_key, 'slot' => $ref->slot])->all();
                foreach ($asset->references as $ref) {
                    if (! array_key_exists($ref->owner_key, $owners[$ref->owner_type] ?? [])) {
                        $problems[] = ['asset' => $asset->id, 'problem' => 'Missing/unknown reference owner', 'owner' => $ref->owner_type, 'key' => $ref->owner_key];
                    }
                    if ($ref->media_variant_id && ! $asset->variants->contains('id', $ref->media_variant_id)) {
                        $problems[] = ['asset' => $asset->id, 'problem' => 'Variant reference does not belong to asset'];
                    }
                }
            }
            // Cross-check actual cover/gallery foreign keys as well as the centralized reference registry.
            foreach (['products' => ['cover_media_id'], 'product_categories' => ['cover_media_id'], 'product_sizes' => ['cover_media_id'], 'finishes' => ['cover_media_id'], 'projects' => ['cover_media_id'], 'project_categories' => ['cover_media_id'], 'project_gallery_items' => ['media_asset_id', 'poster_media_id']] as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        continue;
                    }
                    foreach (DB::table($table)->whereNotNull($column)->get() as $row) {
                        $id = $row->$column;
                        $associations[$id][] = ['owner' => $table, 'key' => (string) $row->id, 'slot' => $column, 'slug' => $row->slug ?? null];
                        if (! isset($assetIds[$id])) {
                            $problems[] = ['asset' => $id, 'problem' => 'Broken content media foreign key', 'owner' => $table, 'key' => $row->id];
                        }
                    }
                }
            }
            foreach ($assets as $asset) {
                $fixture = preg_match('~(?:^|/)(?:synthetic-[^/]*|fixtures?|testing|mockups?|generated)(?:/|$)~i', $asset->source_path) === 1;
                $classification = $fixture ? 'fixture_or_generated_excluded' : ($asset->client_supplied ? 'declared_client_or_user_selected_reference' : 'unconfirmed_provenance_excluded');
                $base = ['asset_id' => $asset->id, 'source_path' => $asset->source_path, 'type' => $asset->type, 'approval' => $asset->approval_state, 'active_revision' => $asset->active_revision, 'classification' => $classification, 'associations' => $associations[$asset->id] ?? []];
                $original = $base + ['kind' => 'original', 'id' => $asset->id, 'source_disk' => $asset->original_disk, 'key' => $asset->original_key, 'sha256' => $asset->checksum, 'bytes' => $asset->bytes, 'mime' => $asset->mime, 'state' => 'private', 'destination' => 'r2_private', 'record' => $asset->getRawOriginal()];
                $objects[] = $this->inspect($original, ! $fixture && $asset->client_supplied);
                $known[$asset->original_disk][$asset->original_key] = true;
                foreach ($asset->variants as $variant) {
                    $published = $variant->state === 'published';
                    $entry = $base + ['kind' => 'variant', 'id' => $variant->id, 'source_disk' => $variant->disk, 'key' => $variant->object_key, 'sha256' => $variant->checksum, 'bytes' => $variant->bytes, 'mime' => match ($variant->format) {
                        'mp4' => 'video/mp4', 'glb' => 'model/gltf-binary', default => 'image/'.$variant->format
                    }, 'state' => $variant->state, 'publication_revision' => $variant->publication_revision, 'destination' => $published ? 'r2_published' : 'r2_private', 'record' => $variant->getRawOriginal()];
                    $objects[] = $this->inspect($entry, ! $fixture && $asset->client_supplied && (! $published || $asset->approval_state === 'approved'));
                    $known[$variant->disk][$variant->object_key] = true;
                    // Publication intentionally retains a private working copy; migrate it privately too.
                    if ($variant->disk === 'local_published' && Storage::disk('local')->exists($variant->object_key)) {
                        $working = array_replace($entry, ['kind' => 'working_copy', 'source_disk' => 'local', 'state' => 'private', 'destination' => 'r2_private']);
                        $objects[] = $this->inspect($working, ! $fixture && $asset->client_supplied);
                        $known['local'][$variant->object_key] = true;
                    }
                }
            }
            foreach (['local', 'local_published'] as $disk) {
                $files = Storage::disk($disk)->allFiles();
                if (count($files) > 100000) {
                    throw new RuntimeException('Storage inventory exceeds the bounded file count.');
                }
                foreach ($files as $key) {
                    if (isset($known[$disk][$key]) || basename($key) === '.gitignore') {
                        continue;
                    }
                    $path = Storage::disk($disk)->path($key);
                    $size = Storage::disk($disk)->size($key);
                    $root = realpath(Storage::disk($disk)->path(''));
                    $safe = ! is_link($path) && realpath($path) && str_starts_with(str_replace('\\', '/', realpath($path)), rtrim(str_replace('\\', '/', $root), '/').'/') && $size <= config('video.max_bytes');
                    $extra[] = ['disk' => $disk, 'key' => $key, 'bytes' => $size, 'sha256' => $safe ? hash_file('sha256', $path) : null, 'mime' => $safe ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : null, 'classification' => 'unregistered_file_manual_review', 'eligible' => false, 'bucket' => null, 'associations' => []];
                }
            }
            foreach ($objects as $object) {
                if ($object['problems'] && ! str_ends_with($object['classification'], '_excluded')) {
                    $problems[] = ['asset' => $object['asset_id'], 'key' => $object['key'], 'problem' => implode('; ', $object['problems'])];
                }
            }
            $eligible = array_values(array_filter($objects, fn ($object) => $object['eligible']));
            $hashes = collect($objects)->groupBy('sha256')->filter(fn ($entries) => count($entries) > 1)->map(fn ($entries) => ['copies' => count($entries), 'keys' => $entries->pluck('key')->unique()->values()->all()])->all();

            return ['version' => 1, 'created_at' => now()->toIso8601String(), 'content_fingerprint' => $this->fingerprint(), 'database' => DB::connection()->getDatabaseName(), 'objects' => $objects, 'unregistered_files' => $extra, 'problems' => $problems, 'duplicate_content' => $hashes, 'summary' => ['assets' => count($assets), 'objects' => count($objects), 'eligible_objects' => count($eligible), 'eligible_bytes' => array_sum(array_column($eligible, 'bytes')), 'private_objects' => count(array_filter($eligible, fn ($object) => $object['destination'] === 'r2_private')), 'published_objects' => count(array_filter($eligible, fn ($object) => $object['destination'] === 'r2_published')), 'blocking_problems' => count($problems), 'unregistered_files' => count($extra), 'estimated_operations_upper_bound' => ['HEAD' => 12 * count($eligible), 'PUT' => 3 * count($eligible), 'GET_integrity_verification' => 3 * count($eligible)]]];
        });
    }

    private function inspect(array $entry, bool $eligible): array
    {
        $problems = [];
        try {
            if ($entry['kind'] === 'original') {
                MediaPath::assertOriginal($entry['key'], $entry['asset_id'], $entry['sha256']);
            } else {
                MediaPath::assertVariant($entry['key'], $entry['asset_id'], $entry['record']['profile_version'], $entry['sha256'], $entry['record']['format']);
            }
            $limit = match ($entry['type']) {
                'video' => config('video.max_bytes'), 'model' => min(8388608, config('viewer.max_bytes')), default => config('media.max_bytes')
            };
            if ($entry['bytes'] > $limit) {
                throw new RuntimeException('Media exceeds its type limit.');
            }
            if (! in_array($entry['source_disk'], ['local', 'local_published', 'r2_private', 'r2_published'], true) || ($entry['state'] === 'private' && ! in_array($entry['source_disk'], ['local', 'r2_private'], true))) {
                throw new RuntimeException('Invalid source storage role.');
            }
            if (in_array($entry['source_disk'], ['local', 'local_published'], true)) {
                $path = Storage::disk($entry['source_disk'])->path($entry['key']);
                $root = realpath(Storage::disk($entry['source_disk'])->path(''));
                if (is_link($path) || ! realpath($path) || ! str_starts_with(str_replace('\\', '/', realpath($path)), rtrim(str_replace('\\', '/', $root), '/').'/')) {
                    throw new RuntimeException('Unsafe source file.');
                }
            }
            app(MediaStorage::class)->verifyStored($entry['source_disk'], $entry['key'], $entry['sha256'], $entry['bytes']);
        } catch (\Throwable) {
            $problems[] = 'Missing, corrupt, over-limit or invalid source object';
        }
        $entry['bucket'] = config('filesystems.disks.'.$entry['destination'].'.bucket') ?: ($entry['destination'] === 'r2_private' ? 'laamach-private' : 'laamach-published');

        return $entry + ['eligible' => $eligible && ! $problems, 'problems' => $problems, 'already_remote' => $entry['source_disk'] === $entry['destination']];
    }
}
