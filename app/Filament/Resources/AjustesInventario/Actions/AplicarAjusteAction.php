<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Actions;

use App\Domain\Exceptions\GrupoOlympoException;
use App\Models\AjusteInventario;
use App\Services\Inventario\AjusteInventarioService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Aplica un ajuste en borrador al inventario.
 */
final class AplicarAjusteAction
{
    public static function make(): Action
    {
        return Action::make('aplicar')
            ->label('Aplicar')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize('aplicar')
            ->visible(fn (AjusteInventario $record): bool => $record->esBorrador())
            ->requiresConfirmation()
            ->modalHeading(fn (AjusteInventario $record): string => "Aplicar el ajuste {$record->numero}")
            ->modalDescription('Las cantidades se sumarán o restarán del inventario y quedarán en el kardex. Un ajuste aplicado no se edita: un error se corrige con otro ajuste.')
            ->modalSubmitActionLabel('Sí, aplicar')
            ->action(function (AjusteInventario $record): void {
                try {
                    app(AjusteInventarioService::class)->aplicar($record);
                } catch (GrupoOlympoException $e) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo aplicar el ajuste')
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->success()
                    ->title("Ajuste {$record->numero} aplicado")
                    ->body('El inventario ya está actualizado.')
                    ->send();
            });
    }
}
