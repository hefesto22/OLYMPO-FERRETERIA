<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Estado de un cambio de precio por costo.
 *
 *  - Automatico: subió el costo y el precio subió solo.
 *  - Pendiente:  bajó el costo; espera que el dueño decida.
 *  - Aplicado:   el dueño aprobó bajar el precio.
 *  - Rechazado:  el dueño decidió mantener el precio.
 *  - Descartado: quedó sin efecto (el costo volvió a subir o se cambió el margen).
 */
enum EstadoCambioPrecio: string implements HasColor, HasIcon, HasLabel
{
    case Pendiente = 'pendiente';
    case Automatico = 'automatico';
    case Aplicado = 'aplicado';
    case Rechazado = 'rechazado';
    case Descartado = 'descartado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente  => 'Por revisar',
            self::Automatico => 'Subió solo',
            self::Aplicado   => 'Precio bajado',
            self::Rechazado  => 'Precio mantenido',
            self::Descartado => 'Sin efecto',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente  => 'warning',
            self::Automatico => 'info',
            self::Aplicado   => 'success',
            self::Rechazado  => 'gray',
            self::Descartado => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pendiente  => Heroicon::OutlinedHandRaised,
            self::Automatico => Heroicon::OutlinedArrowTrendingUp,
            self::Aplicado   => Heroicon::OutlinedArrowTrendingDown,
            self::Rechazado  => Heroicon::OutlinedLockClosed,
            self::Descartado => Heroicon::OutlinedCheck,
        };
    }
}
