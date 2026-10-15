<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Domain\Exceptions\TurnoCajaException;
use App\Enums\EstadoTurno;
use App\Enums\EstadoVenta;
use App\Enums\MetodoPago;
use App\Enums\TipoDocumentoVenta;
use App\Enums\TipoMovimientoCaja;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Turnos de caja: apertura con fondo, movimientos de efectivo y cierre
 * con arqueo. El efectivo esperado es:
 *
 *   fondo inicial + ventas en efectivo + ingresos − retiros
 *
 * Las ventas anuladas no cuentan (su efectivo se devolvió).
 */
final class TurnoCajaService
{
    public function turnoAbierto(User $usuario): ?TurnoCaja
    {
        return TurnoCaja::query()
            ->with('caja')
            ->where('user_id', $usuario->id)
            ->where('estado', EstadoTurno::Abierto)
            ->first();
    }

    public function abrir(Caja $caja, User $cajero, float $fondoInicial): TurnoCaja
    {
        if ($fondoInicial < 0) {
            throw TurnoCajaException::montoInvalido();
        }

        return DB::transaction(function () use ($caja, $cajero, $fondoInicial): TurnoCaja {
            $caja = Caja::query()->whereKey($caja->getKey())->lockForUpdate()->firstOrFail();

            if (! $caja->activo) {
                throw TurnoCajaException::cajaInactiva($caja->nombre);
            }

            if ($caja->turnoAbierto()->exists()) {
                throw TurnoCajaException::cajaOcupada($caja->nombre);
            }

            if ($this->turnoAbierto($cajero) !== null) {
                throw TurnoCajaException::cajeroConTurno();
            }

            $turno = new TurnoCaja;
            $turno->forceFill([
                'caja_id'       => $caja->id,
                'user_id'       => $cajero->id,
                'estado'        => EstadoTurno::Abierto,
                'fondo_inicial' => round($fondoInicial, 2),
                'abierto_at'    => now(),
            ])->save();

            return $turno;
        });
    }

    public function registrarMovimiento(TurnoCaja $turno, TipoMovimientoCaja $tipo, float $monto, string $motivo, User $usuario): MovimientoCaja
    {
        if ($monto <= 0) {
            throw TurnoCajaException::montoInvalido();
        }

        if (! $turno->estaAbierto()) {
            throw TurnoCajaException::cerrado();
        }

        return MovimientoCaja::query()->create([
            'turno_caja_id' => $turno->id,
            'tipo'          => $tipo,
            'monto'         => round($monto, 2),
            'motivo'        => $motivo,
            'user_id'       => $usuario->id,
        ]);
    }

    /**
     * Totales del turno en este momento (para el arqueo y el cierre).
     *
     * @return array{cantidad_ventas: int, anuladas: int, recibos: int, facturas: int, total_ventas: float, total_efectivo: float, total_tarjeta: float, total_transferencia: float, total_ingresos: float, total_egresos: float, efectivo_esperado: float}
     */
    public function resumen(TurnoCaja $turno): array
    {
        $completadas = Venta::query()
            ->where('turno_caja_id', $turno->id)
            ->where('estado', EstadoVenta::Completada);

        $pagos = DB::table('venta_pagos')
            ->whereIn('venta_id', (clone $completadas)->select('id'))
            ->groupBy('metodo')
            ->selectRaw('metodo, SUM(monto) AS total')
            ->pluck('total', 'metodo');

        $porMetodo = static fn (MetodoPago $metodo): float => round((float) ($pagos[$metodo->value] ?? 0), 2);

        $movimientos = MovimientoCaja::query()->where('turno_caja_id', $turno->id);
        $ingresos = round((float) (clone $movimientos)->where('tipo', TipoMovimientoCaja::Ingreso)->sum('monto'), 2);
        $egresos = round((float) (clone $movimientos)->where('tipo', TipoMovimientoCaja::Egreso)->sum('monto'), 2);
        $efectivo = $porMetodo(MetodoPago::Efectivo);

        return [
            'cantidad_ventas'     => (clone $completadas)->count(),
            'anuladas'            => Venta::query()->where('turno_caja_id', $turno->id)->where('estado', EstadoVenta::Anulada)->count(),
            'recibos'             => (clone $completadas)->where('tipo_documento', TipoDocumentoVenta::Recibo)->count(),
            'facturas'            => (clone $completadas)->where('tipo_documento', TipoDocumentoVenta::Factura)->count(),
            'total_ventas'        => round((float) (clone $completadas)->sum('total'), 2),
            'total_efectivo'      => $efectivo,
            'total_tarjeta'       => $porMetodo(MetodoPago::Tarjeta),
            'total_transferencia' => $porMetodo(MetodoPago::Transferencia),
            'total_ingresos'      => $ingresos,
            'total_egresos'       => $egresos,
            'efectivo_esperado'   => round((float) $turno->fondo_inicial + $efectivo + $ingresos - $egresos, 2),
        ];
    }

    /**
     * Cierra el turno con el efectivo contado y guarda la foto de los totales.
     */
    public function cerrar(TurnoCaja $turno, float $efectivoContado, ?string $notas, User $usuario): TurnoCaja
    {
        if ($efectivoContado < 0) {
            throw TurnoCajaException::montoInvalido();
        }

        return DB::transaction(function () use ($turno, $efectivoContado, $notas, $usuario): TurnoCaja {
            $turno = TurnoCaja::query()->whereKey($turno->getKey())->lockForUpdate()->firstOrFail();

            if (! $turno->estaAbierto()) {
                throw TurnoCajaException::cerrado();
            }

            $resumen = $this->resumen($turno);

            $turno->forceFill([
                'estado'              => EstadoTurno::Cerrado,
                'cerrado_at'          => now(),
                'cerrado_por'         => $usuario->id,
                'cantidad_ventas'     => $resumen['cantidad_ventas'],
                'total_ventas'        => $resumen['total_ventas'],
                'total_efectivo'      => $resumen['total_efectivo'],
                'total_tarjeta'       => $resumen['total_tarjeta'],
                'total_transferencia' => $resumen['total_transferencia'],
                'total_ingresos'      => $resumen['total_ingresos'],
                'total_egresos'       => $resumen['total_egresos'],
                'efectivo_esperado'   => $resumen['efectivo_esperado'],
                'efectivo_contado'    => round($efectivoContado, 2),
                'diferencia'          => round($efectivoContado - $resumen['efectivo_esperado'], 2),
                'notas_cierre'        => $notas,
            ])->save();

            return $turno;
        });
    }
}
