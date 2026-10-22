<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class DescuentoNoPermitidoException extends GrupoOlympoException
{
    public static function excede(string $producto, float $solicitado, float $maximo): self
    {
        return new self(sprintf(
            'El descuento de %s%% en "%s" supera tu máximo permitido (%s%%). Pide autorización a un supervisor.',
            cantidadFormato($solicitado, 2),
            $producto,
            cantidadFormato($maximo, 2),
        ));
    }

    /**
     * Los descuentos de las líneas más el global, juntos, pasan el tope.
     */
    public static function excedeVenta(float $solicitado, float $maximo): self
    {
        return new self(sprintf(
            'Entre todos los descuentos, la venta lleva %s%% de rebaja y tu máximo permitido es %s%%. Pide autorización a un supervisor.',
            cantidadFormato($solicitado, 2),
            cantidadFormato($maximo, 2),
        ));
    }

    public static function negativo(): self
    {
        return new self('Un descuento no puede ser negativo.');
    }
}
