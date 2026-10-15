<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CompraPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Compra');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Compra');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Compra');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Compra');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Compra');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:Compra');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:Compra');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Compra');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Compra');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:Compra');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Compra');
    }

    /**
     * Confirmar la compra: suma la mercadería al inventario.
     */
    public function confirmar(AuthUser $authUser): bool
    {
        return $authUser->can('Confirmar:Compra');
    }

    /**
     * Anular una compra confirmada: revierte su entrada al inventario.
     */
    public function anular(AuthUser $authUser): bool
    {
        return $authUser->can('Anular:Compra');
    }
}
