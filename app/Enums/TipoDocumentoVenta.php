<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Documento que sale de una venta: recibo interno (no fiscal) o factura con CAI.
 * Lo elige el cajero en cada venta.
 */
enum TipoDocumentoVenta: string implements HasColor, HasIcon, HasLabel
{
    case Recibo = 'recibo';
    case Factura = 'factura';

    public function getLabel(): string
    {
        return match ($this) {
            self::Recibo  => 'Recibo',
            self::Factura => 'Factura',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Recibo  => 'gray',
            self::Factura => 'primary',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Recibo  => Heroicon::OutlinedDocumentText,
            self::Factura => Heroicon::OutlinedDocumentCheck,
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
