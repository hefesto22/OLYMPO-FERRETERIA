<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ventas\Actions;

use App\Models\Venta;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Abre el ticket de 80 mm en otra pestaña (se imprime solo).
 */
final class TicketVentaAction
{
    public static function make(): Action
    {
        return Action::make('ticket')
            ->label('Ticket')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(fn (Venta $record): string => route('filament.admin.tickets.venta', ['venta' => $record]))
            ->openUrlInNewTab();
    }
}
