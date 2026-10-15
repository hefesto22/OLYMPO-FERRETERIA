<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class VentaInvalidaException extends GrupoOlympoException
{
    public static function sinLineas(): self
    {
        return new self('La venta no tiene productos.');
    }

    public static function productoNoDisponible(string $producto): self
    {
        return new self("\"{$producto}\" no está disponible para la venta (inactivo o eliminado).");
    }

    public static function cantidadInvalida(string $producto): self
    {
        return new self("La cantidad de \"{$producto}\" debe ser mayor que cero.");
    }

    public static function cantidadNoFraccionable(string $producto, string $unidad): self
    {
        return new self("\"{$producto}\" se vende por {$unidad} completo: la cantidad no puede llevar decimales.");
    }

    public static function presentacionInvalida(string $producto): self
    {
        return new self("La presentación elegida no corresponde a \"{$producto}\".");
    }

    public static function clienteNoDisponible(): self
    {
        return new self('El cliente seleccionado no está activo.');
    }

    public static function yaAnulada(string $numero): self
    {
        return new self("La venta {$numero} ya está anulada.");
    }
}
