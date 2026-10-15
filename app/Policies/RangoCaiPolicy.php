<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RangoCaiPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RangoCai');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:RangoCai');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RangoCai');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:RangoCai');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:RangoCai');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:RangoCai');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:RangoCai');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RangoCai');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RangoCai');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:RangoCai');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RangoCai');
    }
}
