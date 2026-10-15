<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class PagoInsuficienteException extends GrupoOlympoException
{
    public static function efectivo(float $falta): self
    {
        return new self('El efectivo recibido no alcanza: faltan '.lempiras($falta).'.');
    }

    public static function excedeTotal(): self
    {
        return new self('Lo pagado con tarjeta y transferencia supera el total de la venta.');
    }

    public static function montoNegativo(): self
    {
        return new self('Los montos de pago no pueden ser negativos.');
    }
}
