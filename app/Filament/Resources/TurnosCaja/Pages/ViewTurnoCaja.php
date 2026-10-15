<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnosCaja\Pages;

use App\Filament\Resources\TurnosCaja\Actions\CerrarTurnoAction;
use App\Filament\Resources\TurnosCaja\Actions\TicketTurnoAction;
use App\Filament\Resources\TurnosCaja\TurnoCajaResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTurnoCaja extends ViewRecord
{
    protected static string $resource = TurnoCajaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TicketTurnoAction::make(),
            CerrarTurnoAction::make(),
        ];
    }
}
