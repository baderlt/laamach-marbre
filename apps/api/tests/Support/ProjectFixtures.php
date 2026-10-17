<?php

namespace Tests\Support;

use App\Media\MediaLibrary;
use App\Media\VideoProcessor;
use App\Models\MediaAsset;
use App\Models\User;
use App\Projects\ProjectWriter;

class ProjectFixtures
{
    public static function copy(string $title = 'Synthetic project'): array
    {
        return ['fr' => ['title' => $title], 'ar' => ['title' => 'اختبار '.$title]];
    }

    public static function category(User $user, string $slug = 'synthetic-usage', array $extra = [])
    {
        return app(ProjectWriter::class)->save(null, $extra + ['slug' => $slug, 'active' => true, 'translations' => CatalogueFixtures::copy($slug)], $user, true);
    }

    public static function videoFile(string $root): string
    {
        if (! app()->environment('testing')) {
            throw new \RuntimeException('Synthetic fixtures require testing.');
        }
        static $bytes;
        $path = $root.'/synthetic-video.mp4';
        if ($bytes === null) {
            app(VideoProcessor::class)->run([config('video.ffmpeg'), '-nostdin', '-v', 'error', '-f', 'lavfi', '-i', 'color=c=gray:s=160x90:r=10:d=2', '-an', '-c:v', 'libx264', '-threads', '1', '-pix_fmt', 'yuv420p', '-metadata', 'title=Synthetic test only', '-metadata:s:v:0', 'handler_name=Synthetic private stream name', '-movflags', '+faststart', '-y', $path]);
            $bytes = file_get_contents($path);
        } else {
            file_put_contents($path, $bytes);
        }

        return $path;
    }

    public static function video(User $user, string $root): MediaAsset
    {
        $library = app(MediaLibrary::class);
        $asset = $library->ingest(self::videoFile($root), 'projects/synthetic-video.mp4', true);
        $revision = $library->process($asset);
        $library->approve($asset, $user);
        $library->publish($asset, $revision, $user);

        return $asset->fresh();
    }
}
