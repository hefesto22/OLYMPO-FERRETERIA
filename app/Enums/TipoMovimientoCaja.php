<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Entrada o salida de efectivo de la caja que no es una venta:
 * pago a un proveedor en efectivo, retiro a caja fuerte, cambio que se agrega.
 */
enum TipoMovimientoCaja: string implements HasColor, HasIcon, HasLabel
{
    case Ingreso = 'ingreso';
    case Egreso = 'egreso';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso de efectivo',
            self::Egreso  => 'Retiro de efectivo',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ingreso => 'success',
            self::Egreso  => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Ingreso => Heroicon::OutlinedArrowDownTray,
            self::Egreso  => Heroicon::OutlinedArrowUpTray,
        };
    }

    /**
     * Normaliza el estado de un formulario Filament (enum o string).
     */
    public static function desdeEstado(mixed $estado): ?self
    {
        if ($estado instanceof self) {
            return $estado;
        }

        return is_string($estado) ? self::tryFrom($estado) : null;
    }
}
