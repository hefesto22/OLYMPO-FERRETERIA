<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ventas\Pages;

use App\Filament\Resources\Ventas\Actions\AnularVentaAction;
use App\Filament\Resources\Ventas\Actions\TicketVentaAction;
use App\Filament\Resources\Ventas\VentaResource;
use Filament\Resources\Pages\ViewRecord;

class ViewVenta extends ViewRecord
{
    protected static string $resource = VentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TicketVentaAction::make(),
            AnularVentaAction::make(),
        ];
    }
}
