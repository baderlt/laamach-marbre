<?php

namespace App\Policies;

use App\Models\SiteContent;
use App\Models\User;

class SiteContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && in_array($user->role, ['super_admin', 'content_manager'], true);
    }

    public function view(User $user, SiteContent $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SiteContent $record): bool
    {
        return $this->viewAny($user);
    }

    public function manageWhatsApp(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function publish(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, SiteContent $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
