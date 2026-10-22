<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Cómo se teclea un descuento de venta (en una línea o en toda la venta).
 *
 *  - Porcentaje: X % del importe.
 *  - Monto:      L X fijos (en una línea, por toda la línea, no por unidad).
 */
enum TipoDescuento: string implements HasLabel
{
    case Porcentaje = 'porcentaje';
    case Monto = 'monto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Porcentaje => 'Porcentaje',
            self::Monto      => 'Lempiras',
        };
    }

    /**
     * Normaliza el estado de un formulario o de Livewire (enum o string);
     * lo que no se reconoce se toma como porcentaje.
     */
    public static function desdeEstado(mixed $estado): self
    {
        if ($estado instanceof self) {
            return $estado;
        }

        return (is_string($estado) ? self::tryFrom($estado) : null) ?? self::Porcentaje;
    }
}
