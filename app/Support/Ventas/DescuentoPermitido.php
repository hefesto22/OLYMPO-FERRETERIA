<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Enums\RolFerreteria;
use App\Models\ConfiguracionEmpresa;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;

/**
 * Tope de descuento por línea (en %) según los roles del usuario.
 * Super admin y administrador: sin tope. Supervisor y cajero: lo que
 * diga "Datos de la empresa". Si tiene varios roles, gana el mayor.
 */
final class DescuentoPermitido
{
    public static function para(User $usuario): float
    {
        if ($usuario->hasRole(Utils::getSuperAdminName()) || $usuario->hasRole(RolFerreteria::Administrador->value)) {
            return 100.0;
        }

        $configuracion = ConfiguracionEmpresa::actual();
        $maximo = 0.0;

        if ($usuario->hasRole(RolFerreteria::Supervisor->value)) {
            $maximo = max($maximo, (float) $configuracion->descuento_max_supervisor);
        }

        if ($usuario->hasRole(RolFerreteria::Cajero->value)) {
            $maximo = max($maximo, (float) $configuracion->descuento_max_cajero);
        }

        return $maximo;
    }
}
