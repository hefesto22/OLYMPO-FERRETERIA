<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum EstadoTurno: string implements HasColor, HasIcon, HasLabel
{
    case Abierto = 'abierto';
    case Cerrado = 'cerrado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::Cerrado => 'Cerrado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Abierto => 'success',
            self::Cerrado => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Abierto => Heroicon::OutlinedLockOpen,
            self::Cerrado => Heroicon::OutlinedLockClosed,
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
