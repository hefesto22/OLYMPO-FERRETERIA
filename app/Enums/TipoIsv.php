<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tratamiento del ISV de un producto.
 *
 * La tasa se lee de config/honduras.php — nunca se escribe el 15% en código.
 */
enum TipoIsv: string implements HasColor, HasLabel
{
    case Gravado15 = 'gravado_15';
    case Gravado18 = 'gravado_18';
    case Exento = 'exento';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gravado15 => 'Gravado 15%',
            self::Gravado18 => 'Gravado 18%',
            self::Exento    => 'Exento',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Gravado15, self::Gravado18 => 'info',
            self::Exento                     => 'gray',
        };
    }

    /**
     * Tasa como fracción (0.15 = 15%).
     */
    public function tasa(): float
    {
        return match ($this) {
            self::Gravado15 => (float) config('honduras.impuestos.isv.tasa_general', 0.15),
            self::Gravado18 => (float) config('honduras.impuestos.isv.tasa_alcohol_tabaco', 0.18),
            self::Exento    => 0.0,
        };
    }

    /**
     * Normaliza el estado de un formulario Filament, que puede venir
     * como instancia del enum o como string.
     */
    public static function desdeEstado(mixed $estado): ?self
    {
        if ($estado instanceof self) {
            return $estado;
        }

        return is_string($estado) ? self::tryFrom($estado) : null;
    }
}
