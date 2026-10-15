<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TurnoCajaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TurnoCaja');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:TurnoCaja');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TurnoCaja');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:TurnoCaja');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:TurnoCaja');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:TurnoCaja');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:TurnoCaja');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TurnoCaja');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TurnoCaja');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:TurnoCaja');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TurnoCaja');
    }

    /**
     * Cerrar un turno de caja con su arqueo.
     */
    public function cerrar(AuthUser $authUser): bool
    {
        return $authUser->can('Cerrar:TurnoCaja');
    }
}
