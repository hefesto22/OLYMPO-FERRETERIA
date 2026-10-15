<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class VentaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Venta');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Venta');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Venta');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Venta');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Venta');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:Venta');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:Venta');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Venta');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Venta');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:Venta');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Venta');
    }

    /**
     * Anular una venta: devuelve lo vendido al inventario.
     */
    public function anular(AuthUser $authUser): bool
    {
        return $authUser->can('Anular:Venta');
    }
}
