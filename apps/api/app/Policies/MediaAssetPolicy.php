<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;
use Filament\Facades\Filament;

class MediaAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessPanel(Filament::getPanel('admin'));
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $this->viewAny($user);
    }

    public function approve(User $user, MediaAsset $asset): bool
    {
        return $user->isSuperAdmin();
    }

    public function publish(User $user, MediaAsset $asset): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        return $user->isSuperAdmin() && ! $asset->active_revision && ! $asset->references()->exists() && ! $asset->imports()->exists() && ! $asset->variants()->where('state', 'published')->exists();
    }

    public function create(User $user): bool
    {
        return false; // M2 intake is the audited importer, not an anonymous or raw-key uploader.
    }

    public function uploadModel(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function uploadImage(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function uploadVideo(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
