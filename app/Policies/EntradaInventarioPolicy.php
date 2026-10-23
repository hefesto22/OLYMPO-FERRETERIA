<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EntradaInventarioPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EntradaInventario');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:EntradaInventario');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EntradaInventario');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:EntradaInventario');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:EntradaInventario');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:EntradaInventario');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:EntradaInventario');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EntradaInventario');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EntradaInventario');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:EntradaInventario');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EntradaInventario');
    }

    /**
     * Confirmar la entrada: suma la mercadería al inventario.
     */
    public function confirmar(AuthUser $authUser): bool
    {
        return $authUser->can('Confirmar:EntradaInventario');
    }

    /**
     * Anular una entrada confirmada: saca del inventario lo que entró.
     */
    public function anular(AuthUser $authUser): bool
    {
        return $authUser->can('Anular:EntradaInventario');
    }
}
