<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AjusteInventarioPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AjusteInventario');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:AjusteInventario');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AjusteInventario');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:AjusteInventario');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:AjusteInventario');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:AjusteInventario');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:AjusteInventario');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AjusteInventario');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AjusteInventario');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:AjusteInventario');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AjusteInventario');
    }

    /**
     * Aplicar el ajuste al inventario.
     */
    public function aplicar(AuthUser $authUser): bool
    {
        return $authUser->can('Aplicar:AjusteInventario');
    }
}
