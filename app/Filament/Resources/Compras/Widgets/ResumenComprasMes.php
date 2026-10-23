<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Widgets;

use App\Enums\TipoDocumentoCompra;
use App\Models\Compra;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo del mes en Compras: cuánto se gastó, cuánto ISV se descuenta de las
 * ventas y cuántas facturas no lo pueden descontar porque no traen RTN.
 */
class ResumenComprasMes extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    /**
     * @return int|array<string, int|null>|null
     */
    protected function getColumns(): int|array|null
    {
        return ['md' => 3];
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $hoy = CarbonImmutable::now();
        $mes = $hoy->translatedFormat('F');

        $delMes = fn (): Builder => Compra::query()->whereBetween('fecha', [$hoy->startOfMonth()->toDateString(), $hoy->endOfMonth()->toDateString()]);
        $facturas = fn (): Builder => $delMes()->where('tipo_documento', TipoDocumentoCompra::Factura);
        $conRtn = fn (Builder $query): Builder => $query->whereNotNull('rtn_proveedor')->where('rtn_proveedor', '!=', '');

        $total = (float) $delMes()->sum('total');
        $cantidad = $delMes()->count();
        $acreditable = (float) $conRtn($facturas())->sum('isv');
        $sinRtn = $facturas()->where('isv', '>', 0)->where(fn (Builder $query): Builder => $query->whereNull('rtn_proveedor')->orWhere('rtn_proveedor', ''))->count();

        return [
            Stat::make('Total del mes', lempiras($total))
                ->description(($cantidad === 1 ? '1 compra' : $cantidad.' compras').' en '.$mes)
                ->descriptionIcon(Heroicon::OutlinedShoppingCart),
            Stat::make('ISV acreditable', lempiras($acreditable))
                ->description('Se descuenta del ISV de tus ventas de '.$mes)
                ->descriptionIcon(Heroicon::OutlinedReceiptPercent)
                ->color('success'),
            Stat::make('Facturas sin RTN', (string) $sinRtn)
                ->description($sinRtn > 0 ? 'Su ISV no se puede descontar: agrega el RTN' : 'Todas traen el RTN del proveedor')
                ->descriptionIcon($sinRtn > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($sinRtn > 0 ? 'warning' : 'gray'),
        ];
    }
}
