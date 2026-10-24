<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Hacia dónde mueve el inventario un ajuste. En un conteo físico cada
 * producto queda en lo contado: unos suben, otros bajan.
 */
enum TipoAjuste: string implements HasColor, HasIcon, HasLabel
{
    case Entrada = 'entrada';
    case Salida = 'salida';
    case Conteo = 'conteo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada (suma al inventario)',
            self::Salida  => 'Salida (resta del inventario)',
            self::Conteo  => 'Conteo (queda lo contado)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Entrada => 'success',
            self::Salida  => 'danger',
            self::Conteo  => 'info',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Entrada => Heroicon::OutlinedPlusCircle,
            self::Salida  => Heroicon::OutlinedMinusCircle,
            self::Conteo  => Heroicon::OutlinedClipboardDocumentCheck,
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
