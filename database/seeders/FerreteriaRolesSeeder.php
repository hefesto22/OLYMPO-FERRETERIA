<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RolFerreteria;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles de trabajo de la ferretería con sus permisos (formato Shield
 * "Accion:Modelo"). Corre después de AdminUserSeeder, que genera los
 * permisos. Se puede volver a correr: sincroniza, no duplica.
 *
 * Los permisos se ajustan después desde Administración → Roles.
 */
class FerreteriaRolesSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $cajero = [
            'View:PuntoDeVenta',
            ...$this->permisos('Cliente', ['ViewAny', 'View', 'Create', 'Update']),
            ...$this->permisos('Venta', ['ViewAny', 'View']),
            ...$this->permisos('TurnoCaja', ['ViewAny', 'View', 'Cerrar']),
            ...$this->permisos('Producto', ['ViewAny', 'View']),
        ];

        $supervisor = [
            ...$cajero,
            'Anular:Venta',
            'Update:TurnoCaja',
            ...$this->permisos('Producto', ['Create', 'Update', 'Delete', 'Restore']),
            ...$this->permisos('Categoria', ['ViewAny', 'View', 'Create', 'Update']),
            ...$this->permisos('Marca', ['ViewAny', 'View', 'Create', 'Update']),
            ...$this->permisos('UnidadMedida', ['ViewAny', 'View', 'Create', 'Update']),
            ...$this->permisos('Proveedor', ['ViewAny', 'View', 'Create', 'Update']),
            ...$this->permisos('Compra', ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Confirmar', 'Anular']),
            ...$this->permisos('AjusteInventario', ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Aplicar']),
            ...$this->permisos('MovimientoInventario', ['ViewAny', 'View']),
            ...$this->permisos('Caja', ['ViewAny', 'View']),
            ...$this->permisos('RangoCai', ['ViewAny', 'View']),
        ];

        $this->rol(RolFerreteria::Cajero, $cajero);
        $this->rol(RolFerreteria::Supervisor, $supervisor);

        // Administrador: todo, menos la gestión de roles y permisos.
        $administrador = Permission::query()
            ->where('name', 'not like', '%:Role')
            ->pluck('name')
            ->all();

        $this->rol(RolFerreteria::Administrador, $administrador);

        $this->command?->info('✓ Roles de la ferretería: administrador, supervisor y cajero.');
    }

    /**
     * @param list<string> $acciones
     *
     * @return list<string>
     */
    private function permisos(string $modelo, array $acciones): array
    {
        return array_map(static fn (string $accion): string => "{$accion}:{$modelo}", $acciones);
    }

    /**
     * @param array<int, string> $permisos
     */
    private function rol(RolFerreteria $rol, array $permisos): void
    {
        $role = Role::query()->firstOrCreate(['name' => $rol->value, 'guard_name' => 'web']);

        // Solo los que existen: si un recurso aún no generó su permiso, no revienta.
        $existentes = Permission::query()->whereIn('name', array_values(array_unique($permisos)))->get();

        $role->syncPermissions($existentes);
    }
}
