<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Ciclo de vida de compras y ajustes de inventario.
 *
 * Borrador: se puede editar o borrar; no toca el inventario.
 * Confirmado: ya movió el inventario; no se edita.
 * Anulado: se revirtió su efecto en el inventario.
 */
enum EstadoDocumento: string implements HasColor, HasIcon, HasLabel
{
    case Borrador = 'borrador';
    case Confirmado = 'confirmado';
    case Anulado = 'anulado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Borrador   => 'Borrador',
            self::Confirmado => 'Confirmado',
            self::Anulado    => 'Anulado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Borrador   => 'gray',
            self::Confirmado => 'success',
            self::Anulado    => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Borrador   => Heroicon::OutlinedPencilSquare,
            self::Confirmado => Heroicon::OutlinedCheckCircle,
            self::Anulado    => Heroicon::OutlinedXCircle,
        };
    }
}
