<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * Una salida (venta, ajuste, anulación de compra) pide más de lo que hay.
 */
final class StockInsuficienteException extends GrupoOlympoException
{
    public static function para(string $producto, float $solicitado, float $disponible, string $unidad = ''): self
    {
        $sufijo = $unidad !== '' ? ' '.$unidad : '';

        return new self(sprintf(
            'Stock insuficiente de "%s": se necesitan %s%s y hay %s%s.',
            $producto,
            cantidadFormato($solicitado),
            $sufijo,
            cantidadFormato($disponible),
            $sufijo,
        ));
    }
}
