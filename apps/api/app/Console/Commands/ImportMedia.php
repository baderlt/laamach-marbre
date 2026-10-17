<?php

namespace App\Console\Commands;

use App\Media\AssetImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportMedia extends Command
{
    protected $signature = 'laamach:media-import {directory} {--apply : Persist private originals, variants and staged mappings} {--client-supplied : Record explicit client-supplied provenance; this is not usage approval}';

    protected $description = 'Inventory/validate client media; dry-run by default, never auto-publish';

    public function handle(AssetImporter $importer): int
    {
        try {
            $result = $importer->run($this->argument('directory'), $this->option('apply'), $this->option('client-supplied'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $result['failed'] ? self::FAILURE : self::SUCCESS;
        } catch (ValidationException $error) {
            $this->error(implode(' ', $error->validator->errors()->all()));

            return self::FAILURE;
        }
    }
}
