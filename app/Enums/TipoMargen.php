<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Cómo se fija el precio de un producto.
 *
 *  - Porcentaje: costo + X % (+ ISV), redondeado al lempira hacia arriba.
 *  - Monto:      costo + L X por unidad base (+ ISV), igual redondeado.
 *  - Manual:     el precio se teclea y el sistema no lo toca.
 */
enum TipoMargen: string implements HasIcon, HasLabel
{
    case Porcentaje = 'porcentaje';
    case Monto = 'monto';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Porcentaje => 'Margen en %',
            self::Monto      => 'Margen en lempiras',
            self::Manual     => 'Precio a mano',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Porcentaje => Heroicon::OutlinedReceiptPercent,
            self::Monto      => Heroicon::OutlinedCurrencyDollar,
            self::Manual     => Heroicon::OutlinedPencilSquare,
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
