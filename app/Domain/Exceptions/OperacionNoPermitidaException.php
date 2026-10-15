<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * Se intenta una operación que el estado del documento no admite
 * (confirmar dos veces, anular un borrador, confirmar sin líneas…).
 */
final class OperacionNoPermitidaException extends GrupoOlympoException
{
    public static function porEstado(string $documento, string $estado, string $accion): self
    {
        return new self("No se puede {$accion} {$documento}: está en estado {$estado}.");
    }

    public static function sinLineas(string $documento): self
    {
        return new self("No se puede procesar {$documento}: no tiene productos.");
    }
}
