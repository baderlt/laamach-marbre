<?php

namespace App\Media;

use App\Models\MediaAsset;
use App\Models\MediaImport;
use App\Models\ProductSize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssetImporter
{
    private const ROOTS = ['brand/hero', 'brand/banners', 'brand/general', 'categories', 'products', 'projects'];

    public function __construct(private ImageProcessor $images, private MediaLibrary $library) {}

    public function run(string $directory, bool $apply = false, bool $clientSupplied = false): array
    {
        $root = realpath($directory);
        if (! $root || ! is_dir($root) || is_link($directory) || @filetype($directory) !== 'dir') {
            throw ValidationException::withMessages(['source' => 'A regular client-assets directory is required.']);
        }
        $manifest = $this->manifest($root);
        $rows = [];
        $seen = [];
        $rootHash = hash('sha256', str_replace('\\', '/', $root));
        $count = 0;
        $found = [];
        foreach (self::ROOTS as $approved) {
            $folder = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $approved);
            if (! file_exists($folder)) {
                continue;
            }
            $this->safeFile($root, $approved, directory: true);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $file) {
                if (++$count > config('media.import_max_files')) {
                    throw ValidationException::withMessages(['source' => 'Import inventory exceeds the bounded entry limit. Split the source directory.']);
                }
                $source = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if ($file->isDir() && ! $file->isLink()) {
                    $this->safeFile($root, $source, directory: true);

                    continue;
                }
                $found[$source] = true;
                $checksum = null;
                try {
                    $path = $this->safeFile($root, $source);
                    $checksum = hash_file('sha256', $path);
                    $isVideo = (new \finfo(FILEINFO_MIME_TYPE))->file($path) === 'video/mp4';
                    $info = $isVideo ? app(VideoProcessor::class)->inspect($path) : $this->images->inspect($path);
                    if (! isset($manifest[$source])) {
                        $rows[] = ['source' => $source, 'checksum' => $checksum, 'status' => 'skipped', 'reason' => 'Ambiguous: explicit manifest mapping required.'];

                        continue;
                    }
                    $mapping = $manifest[$source];
                    if (isset($mapping['sha256']) && $mapping['sha256'] !== $checksum) {
                        throw ValidationException::withMessages(['source' => 'Source differs from its reviewed manifest checksum.']);
                    }
                    if ($isVideo !== ($mapping['role'] === 'project_video')) {
                        throw ValidationException::withMessages(['source' => 'Video assets require the explicit project_video mapping role.']);
                    }
                    $previous = MediaImport::where('root_hash', $rootHash)->where('source', $source)->first();
                    if ($previous) {
                        if ($previous->checksum !== $checksum) {
                            throw ValidationException::withMessages(['source' => 'Previously imported source changed. Supply a new source filename/version; editorial data was preserved.']);
                        }
                        $rows[] = ['source' => $source, 'checksum' => $checksum, 'status' => 'skipped', 'reason' => 'Already imported; stored mapping and editorial values preserved.'];

                        continue;
                    }
                    $duplicate = isset($seen[$checksum]) || MediaAsset::where('checksum', $checksum)->exists();
                    if ($apply) {
                        DB::transaction(function () use ($path, $source, $clientSupplied, $mapping, $rootHash, $checksum) {
                            $asset = $this->library->ingest($path, $source, $clientSupplied);
                            // Serialize imports, processing and editorial/publication operations per asset.
                            $asset = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
                            $this->library->process($asset, config('media.roles.'.$mapping['role'].'.profiles'));
                            foreach ($mapping['translations'] ?? [] as $locale => $copy) {
                                $asset->translations()->firstOrCreate(['locale' => $locale], $copy);
                            }
                            $ledger = new MediaImport;
                            $ledger->forceFill(['root_hash' => $rootHash, 'source' => $source, 'checksum' => $checksum, 'media_asset_id' => $asset->id, 'mapping' => $mapping])->save();
                        }, 3);
                    }
                    $seen[$checksum] = true;
                    $rows[] = ['source' => $source, 'checksum' => $info['checksum'], 'status' => $duplicate ? 'skipped' : 'accepted', 'reason' => $duplicate ? 'Duplicate content; mapping staged on apply without another original.' : ($apply ? 'Imported privately; usage approval pending.' : 'Valid candidate; would import privately.')];
                } catch (\Throwable $error) {
                    $rows[] = ['source' => $source, 'checksum' => $checksum, 'status' => 'failed', 'reason' => $error instanceof ValidationException ? implode(' ', $error->validator->errors()->all()) : 'Import failed; private retry is safe. Check local application diagnostics.'];
                    if (! $error instanceof ValidationException) {
                        report($error);
                    }
                }
            }
        }
        foreach (array_diff_key($manifest, $found) as $source => $mapping) {
            $rows[] = ['source' => $source, 'checksum' => null, 'status' => 'failed', 'reason' => 'Manifest source is missing from the approved inventory.'];
        }

        return ['mode' => $apply ? 'apply' : 'dry-run', 'accepted' => count(array_filter($rows, fn ($row) => $row['status'] === 'accepted')), 'skipped' => count(array_filter($rows, fn ($row) => $row['status'] === 'skipped')), 'failed' => count(array_filter($rows, fn ($row) => $row['status'] === 'failed')), 'files' => $rows];
    }

    private function manifest(string $root): array
    {
        if (! file_exists($root.'/assets-map.json')) {
            return [];
        }
        $file = $this->safeFile($root, 'assets-map.json');
        if (filesize($file) > config('media.manifest_max_bytes')) {
            throw ValidationException::withMessages(['manifest' => 'Manifest exceeds the byte limit.']);
        }
        try {
            $entries = json_decode(file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['manifest' => 'Invalid manifest JSON.']);
        }
        if (! is_array($entries) || ! array_is_list($entries) || count($entries) > config('media.import_max_files')) {
            throw ValidationException::withMessages(['manifest' => 'Manifest must be a bounded JSON list.']);
        }
        $result = [];
        foreach ($entries as $entry) {
            Validator::make(['entry' => $entry], [
                'entry' => 'required|array:source,target_type,target_key,role,order,translations,focal,crop,sha256,client_supplied,size_id',
                'entry.source' => 'required|string|max:512',
                'entry.target_type' => ['required', Rule::in(['brand', 'category', 'product', 'project'])],
                'entry.target_key' => 'required|string|max:120|regex:/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/D',
                'entry.role' => ['required', Rule::in(array_keys(config('media.roles')))],
                'entry.order' => 'required|integer|min:0|max:10000',
                'entry.sha256' => 'sometimes|string|regex:/^[a-f0-9]{64}$/D',
                'entry.client_supplied' => 'sometimes|accepted',
                'entry.size_id' => 'sometimes|uuid|exists:product_sizes,id',
                'entry.translations' => ['sometimes', 'array:'.implode(',', array_column(config('localization.locales'), 'code'))],
                'entry.translations.*' => 'array:alt,caption,credit',
                'entry.translations.*.alt' => 'nullable|string|max:500',
                'entry.translations.*.caption' => 'nullable|string|max:5000',
                'entry.translations.*.credit' => 'nullable|string|max:500',
                'entry.focal' => 'sometimes|array:x,y',
                'entry.focal.x' => 'required_with:entry.focal|numeric|between:0,1',
                'entry.focal.y' => 'required_with:entry.focal|numeric|between:0,1',
                'entry.crop' => ['sometimes', Rule::in(['contain', 'cover'])],
            ])->validate();
            $source = MediaPath::relative($entry['source']);
            if (($entry['role'] === 'product_size') !== isset($entry['size_id'])) {
                throw ValidationException::withMessages(['manifest' => 'A size image requires an explicit existing size ID and product_size role.']);
            }
            if (isset($entry['size_id']) && ! ProductSize::whereKey($entry['size_id'])->whereHas('product', fn ($product) => $product->where('slug', $entry['target_key']))->exists()) {
                throw ValidationException::withMessages(['manifest' => 'Size does not belong to the explicitly mapped product.']);
            }
            $prefix = match ($entry['target_type']) {
                'brand' => 'brand/', 'category' => 'categories/', 'product' => 'products/', 'project' => 'projects/'
            };
            $allowed = count(array_filter(self::ROOTS, fn ($dir) => str_starts_with($source, $dir.'/'))) > 0;
            if (! $allowed || ! str_starts_with($source, $prefix) || config('media.roles.'.$entry['role'].'.target') !== $entry['target_type'] || isset($result[$source])) {
                throw ValidationException::withMessages(['manifest' => 'Conflicting, duplicate or unsafe source mapping.']);
            }
            $result[$source] = $entry;
        }

        return $result;
    }

    private function safeFile(string $root, string $relative, bool $directory = false): string
    {
        MediaPath::relative($relative);
        $path = $root;
        foreach (explode('/', $relative) as $part) {
            $path .= DIRECTORY_SEPARATOR.$part;
            $resolved = realpath($path);
            $normalize = fn ($value) => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', $value)) : $value;
            if (is_link($path) || ($resolved && $normalize($resolved) !== $normalize($path))) {
                throw ValidationException::withMessages(['source' => 'Symbolic links and directory junctions are not accepted.']);
            }
        }
        $real = realpath($path);
        if (! $real || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR) || ($directory ? ! is_dir($real) : ! is_file($real))) {
            throw ValidationException::withMessages(['source' => 'Source escapes the approved root or is not a regular file.']);
        }

        return $real;
    }
}
