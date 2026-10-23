<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Tipos de movimiento del kardex. Cada uno indica si entra o sale stock.
 */
enum TipoMovimientoInventario: string implements HasColor, HasIcon, HasLabel
{
    case InventarioInicial = 'inventario_inicial';
    case Entrada = 'entrada';
    case AnulacionEntrada = 'anulacion_entrada';
    case AjusteEntrada = 'ajuste_entrada';
    case AjusteSalida = 'ajuste_salida';
    case SalidaVenta = 'salida_venta';
    case AnulacionVenta = 'anulacion_venta';

    public function esEntrada(): bool
    {
        return match ($this) {
            self::InventarioInicial,
            self::Entrada,
            self::AjusteEntrada,
            self::AnulacionVenta => true,
            self::AnulacionEntrada,
            self::AjusteSalida,
            self::SalidaVenta => false,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::InventarioInicial => 'Inventario inicial',
            self::Entrada           => 'Entrada de inventario',
            self::AnulacionEntrada  => 'Anulación de entrada',
            self::AjusteEntrada     => 'Ajuste (entrada)',
            self::AjusteSalida      => 'Ajuste (salida)',
            self::SalidaVenta       => 'Venta',
            self::AnulacionVenta    => 'Anulación de venta',
        };
    }

    public function getColor(): string
    {
        if ($this === self::InventarioInicial) {
            return 'info';
        }

        return $this->esEntrada() ? 'success' : 'danger';
    }

    public function getIcon(): Heroicon
    {
        return $this->esEntrada() ? Heroicon::OutlinedArrowDownTray : Heroicon::OutlinedArrowUpTray;
    }
}
