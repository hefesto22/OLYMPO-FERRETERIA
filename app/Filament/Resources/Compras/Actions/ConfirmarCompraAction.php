<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Actions;

use App\Domain\Exceptions\GrupoOlympoException;
use App\Models\Compra;
use App\Services\Inventario\CompraService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Confirma una compra en borrador: suma la mercadería al inventario.
 * Se usa en la tabla y en la vista de la compra.
 */
final class ConfirmarCompraAction
{
    public static function make(): Action
    {
        return Action::make('confirmar')
            ->label('Confirmar')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize('confirmar')
            ->visible(fn (Compra $record): bool => $record->esBorrador())
            ->requiresConfirmation()
            ->modalHeading(fn (Compra $record): string => "Confirmar la compra {$record->numero}")
            ->modalDescription('Las cantidades se sumarán al inventario y se recalculará el costo promedio. Después ya no se podrá editar; si hay un error, se anula.')
            ->modalSubmitActionLabel('Sí, confirmar')
            ->action(function (Compra $record): void {
                try {
                    app(CompraService::class)->confirmar($record);
                } catch (GrupoOlympoException $e) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo confirmar la compra')
                        ->body($e->getMessage())
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->success()
                    ->title("Compra {$record->numero} confirmada")
                    ->body('El inventario ya está actualizado.')
                    ->send();
            });
    }
}
