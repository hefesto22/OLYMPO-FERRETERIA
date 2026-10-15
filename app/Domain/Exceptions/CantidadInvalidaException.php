<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class CantidadInvalidaException extends GrupoOlympoException
{
    public static function noPositiva(float $cantidad): self
    {
        return new self('La cantidad debe ser mayor que cero (se recibió '.cantidadFormato($cantidad).').');
    }
}
