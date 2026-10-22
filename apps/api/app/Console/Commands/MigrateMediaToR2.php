<?php

namespace App\Console\Commands;

use App\Media\MediaMigrationInventory;
use App\Media\R2MediaMigration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class MigrateMediaToR2 extends Command
{
    protected $signature = 'laamach:media-r2 {--output= : New private report directory} {--apply : Requires explicit human approval of the dry-run first} {--plan= : Reviewed plan.json} {--approve= : SHA-256 of the reviewed plan} {--backup= : Consistent database dump created before the migration} {--backup-sha256= : Verified dump checksum}';

    protected $description = 'Read-only media inventory by default; explicitly gated, resumable relocation to R2';

    public function handle(MediaMigrationInventory $inventory, R2MediaMigration $migration): int
    {
        $directory = $this->option('output') ?: storage_path('app/media-migration/'.now()->format('Ymd-His'));
        try {
            $directory = $this->privateDirectory($directory);
            if ($this->option('apply')) {
                $path = $this->option('plan');
                $hash = $this->option('approve');
                if (! is_string($path) || ! is_file($path) || is_link($path) || ! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/D', $hash) || ! hash_equals($hash, hash_file('sha256', $path))) {
                    throw new \RuntimeException('A reviewed, checksum-approved plan is required.');
                }
                $plan = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                $result = $migration->apply($plan, $hash, $directory, $this->option('backup') ?? '', $this->option('backup-sha256') ?? '');
                $this->info('Completed and verified '.count($result['assets']).' assets. Local originals were retained.');

                return self::SUCCESS;
            }
            if (is_file($directory.'/plan.json')) {
                throw new \RuntimeException('Choose a new report directory; existing reviewed plans are immutable.');
            }
            $plan = $inventory->run();
            File::put($directory.'/plan.json', json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            @chmod($directory.'/plan.json', 0600);
            $summary = "# R2 media migration — READ-ONLY DRY-RUN\n\nGenerated: {$plan['created_at']}\n\n";
            foreach ($plan['summary'] as $key => $value) {
                $summary .= '- '.$key.': '.(is_array($value) ? json_encode($value) : $value)."\n";
            }
            $summary .= "\nNo uploads, publication, database reference changes or deletions were performed.\n\nReview plan.json for identifiers, paths, SHA-256, byte sizes, associations, approval/publication status, destination bucket/key, duplicates and validation problems. Unregistered files are excluded pending manual review.\n\nSTOP: explicit approval is required before --apply. A consistent database backup and the reviewed plan checksum are mandatory.\n";
            File::put($directory.'/report.md', $summary);
            File::put($directory.'/broken-references.json', json_encode($plan['problems'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            @chmod($directory.'/report.md', 0600);
            @chmod($directory.'/broken-references.json', 0600);
            $this->line(json_encode(['mode' => 'dry-run', ...$plan['summary'], 'plan_sha256' => hash_file('sha256', $directory.'/plan.json'), 'report_directory' => $directory], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $plan['summary']['blocking_problems'] ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error instanceof \InvalidArgumentException ? 'R2 configuration is incomplete or unsafe. Configure the S3 API endpoint, separate buckets and scoped credentials privately.' : R2MediaMigration::safeFailure($error));

            return self::FAILURE;
        }
    }

    private function privateDirectory(string $path): string
    {
        File::ensureDirectoryExists($path, 0700, true);
        $resolved = str_replace('\\', '/', realpath($path));
        foreach ([public_path(), base_path('../web/public')] as $public) {
            $root = realpath($public);
            if (is_link($path) || ! $resolved || ($root && str_starts_with(strtolower($resolved).'/', strtolower(str_replace('\\', '/', $root)).'/'))) {
                throw new \RuntimeException('Reports must be outside all public web directories.');
            }
        }
        @chmod($resolved, 0700);

        return $resolved;
    }
}
