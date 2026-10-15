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
}
