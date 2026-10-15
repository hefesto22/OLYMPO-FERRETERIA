<?php

declare(strict_types=1);

namespace App\Services\Ventas\Datos;

/**
 * Lo que el cajero pide en una línea: producto, presentación, cantidad
 * y descuento. El precio NO viene de afuera: lo calcula VentaService.
 */
final readonly class LineaVenta
{
    public function __construct(
        public int $productoId,
        public float $cantidad,
        public ?int $presentacionId = null,
        public float $descuentoPct = 0.0,
    ) {}
}
