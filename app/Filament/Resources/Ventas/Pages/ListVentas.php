<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ventas\Pages;

use App\Filament\Pages\PuntoDeVenta;
use App\Filament\Resources\Ventas\VentaResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('puntoDeVenta')
                ->label('Ir al punto de venta')
                ->icon(Heroicon::OutlinedCalculator)
                ->url(PuntoDeVenta::getUrl())
                ->visible(fn (): bool => PuntoDeVenta::canAccess()),
        ];
    }
}
