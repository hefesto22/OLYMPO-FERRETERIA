<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * No se puede emitir factura con CAI: no hay rango activo, venció su
 * fecha límite o se acabaron los números. El cajero puede emitir recibo.
 */
final class CaiException extends GrupoOlympoException
{
    public static function sinRango(string $caja): self
    {
        return new self("La {$caja} no tiene un rango de facturación (CAI) activo. Emite recibo o registra el rango en Facturación.");
    }

    public static function vencido(string $caja, string $fechaLimite): self
    {
        return new self("El CAI de la {$caja} venció el {$fechaLimite}. Solicita uno nuevo al SAR y regístralo.");
    }

    public static function agotado(string $caja): self
    {
        return new self("Se acabaron los números autorizados del CAI de la {$caja}. Registra el nuevo rango.");
    }
}
