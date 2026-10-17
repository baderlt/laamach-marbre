<?php

namespace App\Console\Commands;

use App\Media\ImageProcessor;
use Illuminate\Console\Command;

class MediaCapabilities extends Command
{
    protected $signature = 'laamach:media-capabilities';

    protected $description = 'Verify real WebP/AVIF encode/decode round trips on this PHP runtime';

    public function handle(ImageProcessor $processor): int
    {
        $results = $processor->capabilities();
        $this->line(json_encode($results, JSON_THROW_ON_ERROR));

        return $results['webp'] && ! array_diff(config('media.formats'), array_keys(array_filter($results))) ? self::SUCCESS : self::FAILURE;
    }
}
