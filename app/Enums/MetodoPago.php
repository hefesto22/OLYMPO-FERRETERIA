<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MetodoPago: string implements HasColor, HasIcon, HasLabel
{
    case Efectivo = 'efectivo';
    case Tarjeta = 'tarjeta';
    case Transferencia = 'transferencia';

    public function getLabel(): string
    {
        return match ($this) {
            self::Efectivo      => 'Efectivo',
            self::Tarjeta       => 'Tarjeta',
            self::Transferencia => 'Transferencia',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Efectivo      => 'success',
            self::Tarjeta       => 'info',
            self::Transferencia => 'primary',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Efectivo      => Heroicon::OutlinedBanknotes,
            self::Tarjeta       => Heroicon::OutlinedCreditCard,
            self::Transferencia => Heroicon::OutlinedArrowPath,
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
