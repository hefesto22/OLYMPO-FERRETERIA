<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum EstadoVenta: string implements HasColor, HasIcon, HasLabel
{
    case Completada = 'completada';
    case Anulada = 'anulada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Completada => 'Completada',
            self::Anulada    => 'Anulada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Completada => 'success',
            self::Anulada    => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Completada => Heroicon::OutlinedCheckCircle,
            self::Anulada    => Heroicon::OutlinedXCircle,
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
