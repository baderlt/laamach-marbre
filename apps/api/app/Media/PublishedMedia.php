<?php

namespace App\Media;

use App\Models\MediaAsset;
use App\Models\MediaVariant;

class PublishedMedia
{
    public function forAsset(MediaAsset $asset): array
    {
        if (! $asset->active_revision || $asset->approval_state !== 'approved') {
            return [];
        }

        $variants = $asset->relationLoaded('variants') ? $asset->variants : $asset->variants()->where('state', 'published')->where('publication_revision', $asset->active_revision)->get();

        return $variants->where('state', 'published')->where('publication_revision', $asset->active_revision)->map(fn ($variant) => $this->variant($variant))->values()->all();
    }

    public function variant(MediaVariant $variant): array
    {
        abort_unless($variant->state === 'published' && in_array($variant->disk, ['local_published', 'r2_published'], true), 404);
        MediaPath::assertVariant($variant->object_key, $variant->media_asset_id, $variant->profile_version, $variant->checksum, $variant->format);
        $url = $variant->disk === 'r2_published'
            ? rtrim(config('filesystems.disks.r2_published.url'), '/').'/'.$variant->object_key
            : url('/media/'.$variant->object_key);

        return ['url' => $url, 'width' => $variant->width, 'height' => $variant->height, 'format' => $variant->format, 'profile' => $variant->profile, 'revision' => $variant->publication_revision, 'hash' => $variant->checksum];
    }
}
