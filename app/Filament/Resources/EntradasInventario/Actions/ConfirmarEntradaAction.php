<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntradasInventario\Actions;

use App\Domain\Exceptions\GrupoOlympoException;
use App\Models\EntradaInventario;
use App\Services\Inventario\EntradaInventarioService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Confirma una entrada en borrador: suma la mercadería al inventario.
 * Se usa en la tabla y en la vista de la entrada.
 */
final class ConfirmarEntradaAction
{
    public static function make(): Action
    {
        return Action::make('confirmar')
            ->label('Confirmar')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize('confirmar')
            ->visible(fn (EntradaInventario $record): bool => $record->esBorrador())
            ->requiresConfirmation()
            ->modalHeading(fn (EntradaInventario $record): string => "Confirmar la entrada {$record->numero}")
            ->modalDescription('Las cantidades se sumarán al inventario y se recalculará el costo ponderado. Después ya no se podrá editar; si hay un error, se anula.')
            ->modalSubmitActionLabel('Sí, confirmar')
            ->action(function (EntradaInventario $record): void {
                try {
                    app(EntradaInventarioService::class)->confirmar($record);
                } catch (GrupoOlympoException $e) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo confirmar la entrada')
                        ->body($e->getMessage())
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->success()
                    ->title("Entrada {$record->numero} confirmada")
                    ->body('El inventario ya está actualizado.')
                    ->send();
            });
    }
}
