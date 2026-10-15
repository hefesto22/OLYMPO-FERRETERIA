<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Numeración interna legible de documentos y productos: C-000045.
 *
 * Se basa en el id autoincremental, así que nunca choca entre dos
 * cajeros guardando al mismo tiempo. No sirve para facturas con CAI:
 * esas llevan su propio correlativo autorizado por el SAR.
 */
final class NumeroDocumento
{
    public static function formatear(string $prefijo, int $id): string
    {
        $digitos = (int) config('ferreteria.documentos.digitos', 6);

        return $prefijo.'-'.str_pad((string) $id, $digitos, '0', STR_PAD_LEFT);
    }
}
