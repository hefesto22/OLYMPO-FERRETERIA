<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\Pendientes;
use App\Filament\Widgets\ResumenVentas;
use App\Filament\Widgets\UltimasVentas;
use App\Filament\Widgets\VentasPorDia;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;

/**
 * Escritorio: saludo, los números del día y lo pendiente. Cada widget se
 * muestra según los permisos del usuario (el cajero ve el acceso al punto
 * de venta; el dueño, las ventas y los avisos).
 */
class Escritorio extends Dashboard
{
    /** Misma ruta que el escritorio de Filament: panel → pages.dashboard. */
    protected static ?string $slug = 'dashboard';

    protected static ?string $title = 'Escritorio';

    public function getHeading(): string
    {
        $hora = (int) now()->format('G');
        $saludo = match (true) {
            $hora < 5  => 'Buenas noches',
            $hora < 12 => 'Buenos días',
            $hora < 18 => 'Buenas tardes',
            default    => 'Buenas noches',
        };

        $usuario = Filament::auth()->user();
        $nombre = $usuario instanceof User ? trim($usuario->name) : '';

        return $nombre !== '' ? $saludo.', '.Str::before($nombre, ' ') : $saludo;
    }

    public function getSubheading(): string
    {
        return Str::ucfirst(fechaLarga(now(), true));
    }

    /**
     * @return list<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            ResumenVentas::class,
            VentasPorDia::class,
            Pendientes::class,
            UltimasVentas::class,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getColumns(): array
    {
        return ['md' => 2, 'xl' => 3];
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('puntoDeVenta')
                ->label('Abrir punto de venta')
                ->icon(Heroicon::OutlinedCalculator)
                ->url(fn (): string => PuntoDeVenta::getUrl())
                ->visible(fn (): bool => PuntoDeVenta::canAccess()),
        ];
    }
}
