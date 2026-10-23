<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Qué papel dio el proveedor en una compra.
 *
 * Factura: lleva ISV separado; con el RTN del proveedor, ese ISV se
 * descuenta del ISV de las ventas (crédito fiscal) y va al Libro de
 * Compras. Recibo: solo el total pagado, sin ISV que descontar.
 */
enum TipoDocumentoCompra: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Factura = 'factura';
    case Recibo = 'recibo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Factura => 'Factura',
            self::Recibo  => 'Recibo de compra',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Factura => 'Factura del proveedor: su ISV se descuenta del ISV de tus ventas y entra al Libro de Compras del SAR.',
            self::Recibo  => 'Recibo sin ISV separado: cuenta como gasto, pero no se descuenta del ISV de tus ventas.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Factura => 'info',
            self::Recibo  => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Factura => Heroicon::OutlinedDocumentText,
            self::Recibo  => Heroicon::OutlinedReceiptRefund,
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
