<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnosCaja\Actions;

use App\Models\TurnoCaja;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Ticket de 80 mm con el arqueo del turno cerrado.
 */
final class TicketTurnoAction
{
    public static function make(): Action
    {
        return Action::make('ticketCierre')
            ->label('Ticket de cierre')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (TurnoCaja $record): bool => ! $record->estaAbierto())
            ->url(fn (TurnoCaja $record): string => route('filament.admin.tickets.turno', ['turno' => $record]))
            ->openUrlInNewTab();
    }
}
