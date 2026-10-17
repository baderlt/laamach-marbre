<?php

namespace App\Console\Commands;

use App\Media\ClientProductAssets;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class StageClientProducts extends Command
{
    protected $signature = 'laamach:stage-client-products {directory} {--apply} {--client-supplied}';

    protected $description = 'Dry-run M2 mappings, then optionally stage private client media and incomplete product drafts; never publish';

    public function handle(ClientProductAssets $assets): int
    {
        try {
            $result = $assets->run($this->argument('directory'), (bool) $this->option('apply'), (bool) $this->option('client-supplied'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $result['failed'] ? self::FAILURE : self::SUCCESS;
        } catch (ValidationException $error) {
            $this->error(implode(' ', $error->validator->errors()->all()));

            return self::FAILURE;
        }
    }
}
