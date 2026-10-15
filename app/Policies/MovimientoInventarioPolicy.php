<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MovimientoInventarioPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MovimientoInventario');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:MovimientoInventario');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MovimientoInventario');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:MovimientoInventario');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:MovimientoInventario');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:MovimientoInventario');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:MovimientoInventario');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MovimientoInventario');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MovimientoInventario');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:MovimientoInventario');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MovimientoInventario');
    }
}
