<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\BrandingSetting;
use App\Models\ConfiguracionEmpresa;
use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Ticket de 80 mm de una venta (recibo o factura). Se imprime solo al
 * cargar; con ?imprimir=0 solo se muestra.
 */
final class TicketVentaController extends Controller
{
    public function __invoke(Venta $venta): View
    {
        Gate::authorize('view', $venta);

        $venta->load(['detalles', 'pagos', 'cajero', 'caja']);

        return view('tickets.venta', [
            'venta'   => $venta,
            'empresa' => ConfiguracionEmpresa::actual(),
            'logo'    => BrandingSetting::current()->logo_url,
        ]);
    }
}
