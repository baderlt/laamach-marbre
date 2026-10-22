<?php

namespace App\Media;

use GdImage;
use Illuminate\Validation\ValidationException;

class ImageProcessor
{
    public function inspect(string $path): array
    {
        if (! is_file($path) || is_link($path) || filesize($path) < 1 || filesize($path) > config('media.max_bytes')) {
            $this->reject('Image exceeds the byte limit or is not a regular file.');
        }
        $info = @getimagesize($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! $info || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $info['mime'] !== $mime) {
            $this->reject('Only content-validated JPEG, PNG and WebP images are accepted.');
        }
        $this->bounds($info[0], $info[1]);
        $image = $this->decode(file_get_contents($path));

        return ['checksum' => hash_file('sha256', $path), 'mime' => $mime, 'bytes' => filesize($path), 'width' => imagesx($image), 'height' => imagesy($image)];
    }

    private function bounds(int $width, int $height): void
    {
        if ($width < 1 || $height < 1 || max($width, $height) > config('media.max_dimension') || $width * $height > config('media.max_pixels')) {
            $this->reject('Image exceeds configured dimensions or pixel limit.');
        }
        $needed = $width * $height * 8 + min($width * $height, 2560 * 2560) * 8 + config('media.max_bytes') * 2 + 16 * 1024 * 1024;
        $phpLimit = ini_parse_quantity(ini_get('memory_limit'));
        $budget = $phpLimit > 0 ? min(config('media.memory_budget'), $phpLimit) : config('media.memory_budget');
        if (memory_get_usage(true) + $needed > $budget) {
            $this->reject('Image exceeds the decoding memory budget.');
        }
    }

    public function decode(string $bytes): GdImage
    {
        if (strlen($bytes) > config('media.max_bytes') || ! ($info = @getimagesizefromstring($bytes))) {
            $this->reject('Invalid image bytes.');
        }
        $this->bounds($info[0], $info[1]);
        // GD can return a partial image with warnings. Treat every decode warning as corruption.
        set_error_handler(fn () => throw ValidationException::withMessages(['media' => 'Corrupted image data.']));
        try {
            $image = imagecreatefromstring($bytes);
            if (! $image) {
                $this->reject('Image cannot be decoded.');
            }
        } finally {
            restore_error_handler();
        }

        $orientation = $this->orientation($bytes);
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, in_array($orientation, [4, 5], true) ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($orientation) {
            3 => 180, 5, 6, 7 => -90, 8 => 90, default => 0,
        };

        return $angle ? imagerotate($image, $angle, 0) : $image;
    }

    private function orientation(string $bytes): int
    {
        // Read only the bounded JPEG APP1 / TIFF orientation tag. Do not depend
        // on an optional EXIF extension or retain private camera metadata.
        if (! str_starts_with($bytes, "\xff\xd8")) {
            return 1;
        }
        $length = strlen($bytes);
        for ($offset = 2; $offset + 4 <= $length;) {
            if (ord($bytes[$offset++]) !== 0xFF) {
                break;
            }
            while ($offset < $length && ord($bytes[$offset]) === 0xFF) {
                $offset++;
            }
            if ($offset + 3 > $length) {
                break;
            }
            $marker = ord($bytes[$offset++]);
            if (in_array($marker, [0xDA, 0xD9], true)) {
                break;
            }
            $size = unpack('n', substr($bytes, $offset, 2))[1];
            if ($size < 2 || $offset + $size > $length) {
                break;
            }
            $segment = substr($bytes, $offset + 2, $size - 2);
            $offset += $size;
            if ($marker !== 0xE1 || ! str_starts_with($segment, "Exif\0\0")) {
                continue;
            }
            $tiff = substr($segment, 6);
            $little = str_starts_with($tiff, "II\x2a\0");
            if (strlen($tiff) < 8 || (! $little && ! str_starts_with($tiff, "MM\0\x2a"))) {
                return 1;
            }
            $short = $little ? 'v' : 'n';
            $long = $little ? 'V' : 'N';
            $ifd = unpack($long, substr($tiff, 4, 4))[1];
            if ($ifd < 8 || $ifd + 2 > strlen($tiff)) {
                return 1;
            }
            $count = unpack($short, substr($tiff, $ifd, 2))[1];
            for ($entry = $ifd + 2, $index = 0; $index < $count && $entry + 12 <= strlen($tiff); $index++, $entry += 12) {
                if (unpack($short, substr($tiff, $entry, 2))[1] === 0x112
                    && unpack($short, substr($tiff, $entry + 2, 2))[1] === 3
                    && unpack($long, substr($tiff, $entry + 4, 4))[1] === 1) {
                    $value = unpack($short, substr($tiff, $entry + 8, 2))[1];

                    return $value >= 1 && $value <= 8 ? $value : 1;
                }
            }
        }

        return 1;
    }

    public function capabilities(): array
    {
        $image = imagecreatetruecolor(4, 3);
        $result = [];
        foreach (['webp', 'avif'] as $format) {
            try {
                $bytes = $this->encode($image, $format);
                $decoded = @imagecreatefromstring($bytes);
                $result[$format] = $decoded && imagesx($decoded) === 4 && imagesy($decoded) === 3;
            } catch (\Throwable) {
                $result[$format] = false;
            }
        }

        return $result;
    }

    public function encode(GdImage $image, string $format): string
    {
        $function = match ($format) {
            'webp' => 'imagewebp', 'avif' => 'imageavif', default => ''
        };
        if (! $function || ! function_exists($function)) {
            $this->reject('Encoder unavailable.');
        }
        ob_start();
        try {
            $ok = @$function($image, null, config("media.quality.$format"));
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (! $ok || ! $bytes || strlen($bytes) > config('media.max_bytes') || ! ($info = @getimagesizefromstring($bytes)) || $info['mime'] !== 'image/'.$format || $info[0] !== imagesx($image) || $info[1] !== imagesy($image) || ! @imagecreatefromstring($bytes)) {
            $this->reject('Image encoding/round-trip verification failed.');
        }

        return $bytes;
    }

    public function derivatives(string $original, array $profiles): \Generator
    {
        $source = $this->decode($original);
        foreach ($profiles as $name) {
            $profile = config("media.profiles.$name");
            if (! $profile) {
                $this->reject('Unknown derivative profile.');
            }
            $ratio = min(1, $profile['edge'] / max(imagesx($source), imagesy($source)));
            $width = max(1, (int) round(imagesx($source) * $ratio));
            $height = max(1, (int) round(imagesy($source) * $ratio));
            $target = imagecreatetruecolor($width, $height);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
            foreach (config('media.formats') as $format) {
                $settings = [$profile, config("media.quality.$format"), $format, config('media.encoder_version'), GD_VERSION, phpversion(), 'fit-no-upscale-strip'];
                $version = $name.'-'.$profile['version'].'-'.substr(hash('sha256', json_encode($settings, JSON_THROW_ON_ERROR)), 0, 16);
                $bytes = $this->encode($target, $format);
                yield ['profile' => $name, 'profile_version' => $version, 'format' => $format, 'width' => $width, 'height' => $height, 'checksum' => hash('sha256', $bytes), 'bytes' => strlen($bytes), 'content' => $bytes, 'metadata' => ['settings' => $settings, 'metadata_stripped' => true]];
            }
            unset($target);
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['media' => $message]);
    }
}
