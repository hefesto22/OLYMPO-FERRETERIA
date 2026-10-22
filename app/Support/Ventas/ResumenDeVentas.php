<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Enums\EstadoVenta;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Cifras de ventas para el Escritorio. Solo cuentan las ventas completadas
 * (las anuladas no suman) y las fechas son las del sistema.
 */
final class ResumenDeVentas
{
    /**
     * Total y número de ventas de cada día entre las dos fechas, incluidos
     * los días sin ventas (en cero), en orden.
     *
     * @return array<string, array{total: float, cantidad: int}> llave: fecha Y-m-d
     */
    public static function porDia(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $inicio = CarbonImmutable::parse($desde)->startOfDay();
        $fin = CarbonImmutable::parse($hasta)->endOfDay();

        $filas = Venta::query()
            ->toBase()
            ->where('estado', EstadoVenta::Completada->value)
            ->whereBetween('created_at', [$inicio, $fin])
            ->selectRaw('DATE(created_at) AS dia, SUM(total) AS total, COUNT(*) AS cantidad')
            ->groupByRaw('DATE(created_at)')
            ->get();

        $dias = [];

        for ($dia = $inicio; $dia->lessThanOrEqualTo($fin); $dia = $dia->addDay()) {
            $dias[$dia->format('Y-m-d')] = ['total' => 0.0, 'cantidad' => 0];
        }

        foreach ($filas as $fila) {
            $clave = substr((string) $fila->dia, 0, 10);

            if (isset($dias[$clave])) {
                $dias[$clave] = ['total' => round((float) $fila->total, 2), 'cantidad' => (int) $fila->cantidad];
            }
        }

        return $dias;
    }

    /**
     * Total y número de ventas entre dos momentos.
     *
     * @return array{total: float, cantidad: int}
     */
    public static function entre(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $fila = Venta::query()
            ->toBase()
            ->where('estado', EstadoVenta::Completada->value)
            ->whereBetween('created_at', [$desde, $hasta])
            ->selectRaw('COALESCE(SUM(total), 0) AS total, COUNT(*) AS cantidad')
            ->first();

        return [
            'total'    => round((float) ($fila->total ?? 0), 2),
            'cantidad' => (int) ($fila->cantidad ?? 0),
        ];
    }

    /**
     * Cambio en % de un periodo frente al anterior; null si el anterior fue
     * cero (no hay contra qué comparar).
     */
    public static function variacion(float $actual, float $anterior): ?float
    {
        return $anterior > 0 ? ($actual - $anterior) / $anterior * 100 : null;
    }
}
