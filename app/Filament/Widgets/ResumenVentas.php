<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Support\Ventas\ResumenDeVentas;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Los números del día en el Escritorio: ventas de hoy, ticket promedio,
 * ventas del mes y productos por pedir, cada uno con su mini gráfica.
 */
class ResumenVentas extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can('viewAny', Venta::class);
    }

    /**
     * @return int|array<string, int|null>|null
     */
    protected function getColumns(): int|array|null
    {
        return ['md' => 2, 'xl' => 4];
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $hoy = CarbonImmutable::now();
        $semana = ResumenDeVentas::porDia($hoy->subDays(6), $hoy);
        $deHoy = $semana[$hoy->format('Y-m-d')] ?? ['total' => 0.0, 'cantidad' => 0];
        $deAyer = $semana[$hoy->subDay()->format('Y-m-d')] ?? ['total' => 0.0, 'cantidad' => 0];

        $stats = [
            $this->ventasDeHoy($deHoy, $deAyer, $semana),
            $this->ticketPromedio($deHoy, $hoy, $semana),
            $this->ventasDelMes($hoy),
        ];

        $usuario = Filament::auth()->user();

        if ($usuario instanceof User && $usuario->can('viewAny', Producto::class)) {
            $stats[] = $this->porPedir();
        }

        return $stats;
    }

    /**
     * @param array{total: float, cantidad: int} $deHoy
     * @param array{total: float, cantidad: int} $deAyer
     * @param array<string, array{total: float, cantidad: int}> $semana
     */
    private function ventasDeHoy(array $deHoy, array $deAyer, array $semana): Stat
    {
        $ventas = $deHoy['cantidad'] === 1 ? '1 venta' : $deHoy['cantidad'].' ventas';
        $variacion = ResumenDeVentas::variacion($deHoy['total'], $deAyer['total']);
        $stat = Stat::make('Ventas de hoy', lempiras($deHoy['total']))
            ->chart(array_values(array_map(static fn (array $dia): float => $dia['total'], $semana)))
            ->chartColor('primary');

        if ($variacion === null) {
            return $stat->description($ventas);
        }

        return $stat
            ->description($ventas.', '.number_format(abs($variacion), 0).' % '.($variacion >= 0 ? 'más' : 'menos').' que ayer')
            ->descriptionIcon($variacion >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
            ->descriptionColor($variacion >= 0 ? 'success' : 'danger');
    }

    /**
     * @param array{total: float, cantidad: int} $deHoy
     * @param array<string, array{total: float, cantidad: int}> $semana
     */
    private function ticketPromedio(array $deHoy, CarbonImmutable $hoy, array $semana): Stat
    {
        $mes = ResumenDeVentas::entre($hoy->subDays(29)->startOfDay(), $hoy);
        $promedio = $deHoy['cantidad'] > 0 ? $deHoy['total'] / $deHoy['cantidad'] : 0.0;
        $promedioMes = $mes['cantidad'] > 0 ? $mes['total'] / $mes['cantidad'] : 0.0;

        return Stat::make('Ticket promedio de hoy', lempiras($promedio))
            ->description('En 30 días: '.lempiras($promedioMes))
            ->chart(array_values(array_map(
                static fn (array $dia): float => $dia['cantidad'] > 0 ? round($dia['total'] / $dia['cantidad'], 2) : 0.0,
                $semana,
            )))
            ->chartColor('gray');
    }

    private function ventasDelMes(CarbonImmutable $hoy): Stat
    {
        $inicio = $hoy->startOfMonth();
        $mes = ResumenDeVentas::entre($inicio, $hoy);
        $anterior = ResumenDeVentas::entre($inicio->subMonthNoOverflow(), $hoy->subMonthNoOverflow());
        $variacion = ResumenDeVentas::variacion($mes['total'], $anterior['total']);
        $dias = ResumenDeVentas::porDia($inicio, $hoy);

        $stat = Stat::make('Ventas del mes', lempiras($mes['total']))
            ->chart(array_values(array_map(static fn (array $dia): float => $dia['total'], $dias)))
            ->chartColor('gray');

        if ($variacion === null) {
            return $stat->description($mes['cantidad'] === 1 ? '1 venta' : $mes['cantidad'].' ventas');
        }

        return $stat
            ->description(number_format(abs($variacion), 0).' % '.($variacion >= 0 ? 'más' : 'menos').' que el mes pasado a esta fecha')
            ->descriptionIcon($variacion >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
            ->descriptionColor($variacion >= 0 ? 'success' : 'danger');
    }

    private function porPedir(): Stat
    {
        $bajoMinimo = Producto::query()->bajoMinimo()->count();

        return Stat::make('Productos por pedir', (string) $bajoMinimo)
            ->description($bajoMinimo > 0 ? 'En su existencia mínima o debajo' : 'Todo con existencia')
            ->descriptionIcon($bajoMinimo > 0 ? Heroicon::ExclamationTriangle : Heroicon::CheckCircle)
            ->descriptionColor($bajoMinimo > 0 ? 'warning' : 'success')
            ->url(ProductoResource::getUrl('index'));
    }
}
