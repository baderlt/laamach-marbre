<?php

namespace App\Policies;

use App\Models\CatalogueRecord;
use App\Models\User;

class CataloguePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && in_array($user->role, ['super_admin', 'content_manager'], true);
    }

    public function view(User $user, CatalogueRecord $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, CatalogueRecord $record): bool
    {
        return $this->viewAny($user);
    }

    public function publish(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, CatalogueRecord $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
