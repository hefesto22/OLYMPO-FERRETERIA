<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class TurnoCajaException extends GrupoOlympoException
{
    public static function sinTurno(): self
    {
        return new self('No tienes un turno de caja abierto. Abre uno para poder vender.');
    }

    public static function cajaOcupada(string $caja): self
    {
        return new self("La {$caja} ya tiene un turno abierto.");
    }

    public static function cajeroConTurno(): self
    {
        return new self('Ya tienes un turno abierto en otra caja. Ciérralo antes de abrir uno nuevo.');
    }

    public static function cajaInactiva(string $caja): self
    {
        return new self("La {$caja} está desactivada.");
    }

    public static function cerrado(): self
    {
        return new self('El turno ya está cerrado.');
    }

    public static function noEsTuTurno(): self
    {
        return new self('Este turno pertenece a otro cajero.');
    }

    public static function montoInvalido(): self
    {
        return new self('El monto debe ser mayor que cero.');
    }
}
