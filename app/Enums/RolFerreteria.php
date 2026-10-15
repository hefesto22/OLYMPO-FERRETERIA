<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Roles de trabajo de la ferretería (además de super_admin y panel_user
 * de Shield). Los permisos de cada uno los asigna FerreteriaRolesSeeder;
 * el tope de descuento se configura en Datos de la empresa.
 */
enum RolFerreteria: string implements HasLabel
{
    case Administrador = 'administrador';
    case Supervisor = 'supervisor';
    case Cajero = 'cajero';

    public function getLabel(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Supervisor    => 'Supervisor',
            self::Cajero        => 'Cajero',
        };
    }

    /**
     * @return list<string>
     */
    public static function nombres(): array
    {
        return array_map(static fn (self $rol): string => $rol->value, self::cases());
    }
}
