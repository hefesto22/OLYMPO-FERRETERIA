<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Venta;
use App\Support\Ventas\ResumenDeVentas;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Barras con lo vendido cada día de las últimas dos semanas.
 */
class VentasPorDia extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Ventas de los últimos 14 días';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 2];

    public static function canView(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can('viewAny', Venta::class);
    }

    public function getDescription(): ?string
    {
        $dias = $this->dias();
        $total = array_sum(array_map(static fn (array $dia): float => $dia['total'], $dias));

        return 'En total '.lempiras($total).'.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $dias = $this->dias();

        return [
            'datasets' => [
                [
                    'label'           => 'Ventas',
                    'data'            => array_values(array_map(static fn (array $dia): float => $dia['total'], $dias)),
                    'borderRadius'    => 6,
                    'maxBarThickness' => 32,
                ],
            ],
            'labels' => array_map(
                static fn (string $fecha): string => CarbonImmutable::parse($fecha)->translatedFormat('D j'),
                array_keys($dias),
            ),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (contexto) => 'L. ' + contexto.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { callback: (valor) => 'L. ' + valor.toLocaleString('en-US') },
                    },
                },
            }
        JS);
    }

    /**
     * @return array<string, array{total: float, cantidad: int}>
     */
    private function dias(): array
    {
        $hoy = CarbonImmutable::now();

        return ResumenDeVentas::porDia($hoy->subDays(13), $hoy);
    }
}
