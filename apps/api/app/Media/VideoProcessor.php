<?php

namespace App\Media;

use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class VideoProcessor
{
    public function run(array $command): string
    {
        try {
            $process = new Process($command, timeout: config('video.timeout'));
            $process->run();
            if (! $process->isSuccessful()) {
                $this->reject('Video inspection/remux failed. Supply a valid supported MP4.');
            }

            return $process->getOutput();
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable) {
            $this->reject('FFmpeg/ffprobe unavailable or timed out. Video processing is disabled until the worker is configured.');
        }
    }

    private function input(string $path): array
    {
        return ['-max_alloc', '67108864', '-protocol_whitelist', 'file', '-f', 'mov', '-enable_drefs', '0', '-use_absolute_path', '0', '-i', $path];
    }

    public function inspect(string $path): array
    {
        foreach (['max_bytes' => 52428800, 'max_duration' => 60, 'max_dimension' => 1920, 'max_pixels' => 2073600, 'max_fps' => 30, 'timeout' => 45] as $key => $ceiling) {
            if (! is_int(config('video.'.$key)) || config('video.'.$key) < 1 || config('video.'.$key) > $ceiling) {
                $this->reject('Invalid bounded video configuration.');
            }
        }
        if (! is_file($path) || is_link($path) || filesize($path) < 32 || filesize($path) > config('video.max_bytes')) {
            $this->reject('Video exceeds its byte limit or is not a regular file.');
        }
        $stream = fopen($path, 'rb');
        $header = fread($stream, 64);
        fclose($stream);
        if (! in_array((new \finfo(FILEINFO_MIME_TYPE))->file($path), config('video.mime_types'), true) || substr($header, 4, 4) !== 'ftyp' || ! in_array(substr($header, 8, 4), ['isom', 'iso2', 'mp41', 'mp42', 'avc1'], true)) {
            $this->reject('Only supported MP4 file content is accepted.');
        }
        // Parse only explicit MOV/MP4 demuxing, with network and external data references disabled.
        $json = $this->run([config('video.ffprobe'), '-v', 'error', ...$this->input($path), '-show_entries', 'format=format_name,duration:stream=codec_type,codec_name,codec_tag_string,width,height,pix_fmt,avg_frame_rate,channels,sample_rate:stream_disposition=attached_pic', '-of', 'json']);
        $probe = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        $streams = $probe['streams'] ?? [];
        $videos = array_values(array_filter($streams, fn ($item) => ($item['codec_type'] ?? '') === 'video'));
        $audio = array_values(array_filter($streams, fn ($item) => ($item['codec_type'] ?? '') === 'audio'));
        $duration = (float) ($probe['format']['duration'] ?? 0);
        if (count($videos) !== 1 || count($audio) > 1 || count($streams) !== count($videos) + count($audio) || ! is_finite($duration) || $duration <= 0 || $duration > config('video.max_duration')) {
            $this->reject('Video must have one video track, optional audio and a bounded measured duration.');
        }
        $video = $videos[0];
        [$numerator, $denominator] = array_pad(explode('/', $video['avg_frame_rate'] ?? '0/0'), 2, 0);
        $fps = (float) $denominator > 0 ? (float) $numerator / (float) $denominator : 0;
        $width = (int) ($video['width'] ?? 0);
        $height = (int) ($video['height'] ?? 0);
        if (($video['codec_name'] ?? '') !== 'h264' || ($video['codec_tag_string'] ?? '') !== 'avc1' || ($video['pix_fmt'] ?? '') !== 'yuv420p' || ! empty($video['disposition']['attached_pic']) || $width < 1 || $height < 1 || max($width, $height) > config('video.max_dimension') || $width * $height > config('video.max_pixels') || $fps <= 0 || $fps > config('video.max_fps')) {
            $this->reject('Require H.264/avc1 yuv420p MP4 within configured dimensions and frame rate.');
        }
        if ($audio && (($audio[0]['codec_name'] ?? '') !== 'aac' || ($audio[0]['channels'] ?? 0) < 1 || $audio[0]['channels'] > 2 || ($audio[0]['sample_rate'] ?? 0) > 48000)) {
            $this->reject('Audio must be mono/stereo AAC at most 48 kHz.');
        }
        $this->run([config('video.ffmpeg'), '-nostdin', '-v', 'error', '-xerror', '-threads', '1', ...$this->input($path), '-map', '0:v:0', '-map', '0:a:0?', '-f', 'null', '-']);

        return ['checksum' => hash_file('sha256', $path), 'mime' => 'video/mp4', 'bytes' => filesize($path), 'width' => $width, 'height' => $height, 'duration' => $duration];
    }

    public function runtime(string $source, string $output): array
    {
        $this->inspect($source);
        $this->run([config('video.ffmpeg'), '-nostdin', '-v', 'error', '-xerror', ...$this->input($source), '-map', '0:v:0', '-map', '0:a:0?', '-c', 'copy', '-map_metadata', '-1', '-map_metadata:s', '-1', '-map_chapters', '-1', '-movflags', '+faststart', '-f', 'mp4', '-y', $output]);

        return $this->inspect($output);
    }

    public function version(): string
    {
        $ffmpeg = strtok($this->run([config('video.ffmpeg'), '-version']), "\n");
        $ffprobe = strtok($this->run([config('video.ffprobe'), '-version']), "\n");

        return 'video-'.config('video.version').'-'.substr(hash('sha256', json_encode([config('video'), $ffmpeg, $ffprobe], JSON_THROW_ON_ERROR)), 0, 16);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['video' => $message]);
    }
}
