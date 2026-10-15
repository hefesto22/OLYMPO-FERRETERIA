<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CambioPrecioPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CambioPrecio');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:CambioPrecio');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CambioPrecio');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:CambioPrecio');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:CambioPrecio');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:CambioPrecio');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:CambioPrecio');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CambioPrecio');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CambioPrecio');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:CambioPrecio');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CambioPrecio');
    }

    /**
     * Bajar o mantener el precio cuando bajó el costo (el dueño).
     */
    public function aprobar(AuthUser $authUser): bool
    {
        return $authUser->can('Aprobar:CambioPrecio');
    }
}
