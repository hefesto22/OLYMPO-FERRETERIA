<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tickets;

use App\Enums\EstadoVenta;
use App\Enums\TipoDocumentoVenta;
use App\Http\Controllers\Controller;
use App\Models\ConfiguracionEmpresa;
use App\Models\TurnoCaja;
use App\Models\Venta;
use App\Services\Ventas\TurnoCajaService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Ticket de 80 mm del arqueo de un turno, con espacio para firmas.
 */
final class TicketTurnoController extends Controller
{
    public function __invoke(TurnoCaja $turno, TurnoCajaService $servicio): View
    {
        Gate::authorize('view', $turno);

        $turno->load(['caja', 'cajero', 'cerradoPor', 'movimientos']);

        $resumen = $turno->estaAbierto()
            ? $servicio->resumen($turno)
            : [
                'cantidad_ventas'     => $turno->cantidad_ventas,
                'total_ventas'        => (float) $turno->total_ventas,
                'total_efectivo'      => (float) $turno->total_efectivo,
                'total_tarjeta'       => (float) $turno->total_tarjeta,
                'total_transferencia' => (float) $turno->total_transferencia,
                'total_ingresos'      => (float) $turno->total_ingresos,
                'total_egresos'       => (float) $turno->total_egresos,
                'efectivo_esperado'   => (float) $turno->efectivo_esperado,
            ];

        $ventas = Venta::query()->where('turno_caja_id', $turno->id);

        // Las facturas anuladas también consumieron número: se cuentan para cuadrar con el SAR.
        $facturas = (clone $ventas)->where('tipo_documento', TipoDocumentoVenta::Factura);

        return view('tickets.turno', [
            'turno'    => $turno,
            'empresa'  => ConfiguracionEmpresa::actual(),
            'resumen'  => $resumen,
            'facturas' => [
                'cantidad' => (clone $facturas)->count(),
                'primera'  => (clone $facturas)->min('numero'),
                'ultima'   => (clone $facturas)->max('numero'),
            ],
            'recibos'  => (clone $ventas)->where('tipo_documento', TipoDocumentoVenta::Recibo)->count(),
            'anuladas' => (clone $ventas)->where('estado', EstadoVenta::Anulada)->count(),
        ]);
    }
}
