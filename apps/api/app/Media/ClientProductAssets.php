<?php

namespace App\Media;

use App\Catalogue\CatalogueWriter;
use App\Models\MediaImport;
use Illuminate\Validation\ValidationException;

class ClientProductAssets
{
    public function run(string $directory, bool $apply, bool $clientSupplied): array
    {
        abort_unless(app()->runningInConsole(), 403);
        if (! $clientSupplied) {
            throw ValidationException::withMessages(['source' => 'Explicit client-supplied provenance is required.']);
        }
        $importer = app(AssetImporter::class);
        $dry = $importer->run($directory);
        $ambiguous = array_filter($dry['files'], fn ($row) => str_contains($row['reason'], 'Ambiguous:'));
        if ($dry['failed'] || $ambiguous) {
            throw ValidationException::withMessages(['source' => 'Dry-run must have no failed or ambiguous files before applying.']);
        }
        $manifest = json_decode(file_get_contents(realpath($directory).'/assets-map.json'), true, 32, JSON_THROW_ON_ERROR);
        $groups = [];
        foreach ($manifest as $entry) {
            if ($entry['target_type'] !== 'product' || ! in_array($entry['role'], ['product_cover', 'product_gallery', 'product_size'], true) || ($entry['client_supplied'] ?? null) !== true || empty($entry['sha256'])) {
                throw ValidationException::withMessages(['manifest' => 'Only explicit checksum-pinned client product mappings can create drafts.']);
            }
            $groups[$entry['target_key']][] = $entry;
        }
        foreach ($groups as $entries) {
            if (count(array_filter($entries, fn ($entry) => $entry['role'] === 'product_cover')) !== 1) {
                throw ValidationException::withMessages(['manifest' => 'Each mapped product requires exactly one cover.']);
            }
        }
        $duplicates = count(array_filter($dry['files'], fn ($row) => str_contains($row['reason'], 'Duplicate content')));
        if (! $apply) {
            return $dry + ['duplicates' => $duplicates, 'ambiguous' => 0, 'products' => []];
        }
        $result = $importer->run($directory, true, true);
        if ($result['failed']) {
            return $result + ['duplicates' => $duplicates, 'ambiguous' => 0, 'products' => [], 'note' => 'No business records staged because media apply failed. Private media may be safely retried.'];
        }
        $rootHash = hash('sha256', str_replace('\\', '/', realpath($directory)));
        $ledger = MediaImport::where('root_hash', $rootHash)->get()->keyBy('source');
        $products = [];
        foreach ($groups as $slug => $entries) {
            usort($entries, fn ($a, $b) => [$a['order'], $a['source']] <=> [$b['order'], $b['source']]);
            $cover = null;
            $gallery = [];
            foreach ($entries as $entry) {
                $id = $ledger->get($entry['source'])?->media_asset_id;
                if (! $id) {
                    throw ValidationException::withMessages(['manifest' => 'Missing applied media ledger entry.']);
                }
                if ($entry['role'] === 'product_cover') {
                    $cover = $id;
                } elseif ($entry['role'] === 'product_gallery') {
                    $gallery[] = $id;
                }
            }
            $products[] = app(CatalogueWriter::class)->stageClientDraft($slug, ucwords(str_replace('-', ' ', $slug)), $cover, $gallery);
            foreach ($entries as $entry) {
                if ($entry['role'] !== 'product_size') {
                    continue;
                }
                try {
                    $status = app(CatalogueWriter::class)->stageConfirmedSizeImage($slug, $entry['size_id'], $ledger->get($entry['source'])->media_asset_id);
                } catch (ValidationException) {
                    $status = 'awaiting-approved-published-size-media';
                }
                $products[] = ['slug' => $slug, 'size_id' => $entry['size_id'], 'result' => $status];
            }
        }

        return $result + ['duplicates' => $duplicates, 'ambiguous' => 0, 'products' => $products];
    }
}
