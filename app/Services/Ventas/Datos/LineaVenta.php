<?php

declare(strict_types=1);

namespace App\Services\Ventas\Datos;

use App\Enums\TipoDescuento;

/**
 * Lo que el cajero pide en una línea: producto, presentación, cantidad
 * y descuento (en % o en lempiras por toda la línea). El precio NO viene
 * de afuera: lo calcula VentaService.
 */
final readonly class LineaVenta
{
    public function __construct(
        public int $productoId,
        public float $cantidad,
        public ?int $presentacionId = null,
        public float $descuento = 0.0,
        public TipoDescuento $tipoDescuento = TipoDescuento::Porcentaje,
    ) {}
}
