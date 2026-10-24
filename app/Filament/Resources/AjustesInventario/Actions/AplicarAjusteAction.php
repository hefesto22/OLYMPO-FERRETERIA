<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Actions;

use App\Domain\Exceptions\GrupoOlympoException;
use App\Models\AjusteInventario;
use App\Models\User;
use App\Services\Inventario\AjusteInventarioService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Aplica un ajuste en borrador al inventario. `aplicar()` también lo
 * usan los modales de crear y editar ("Aplicar ajuste").
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
            ->modalDescription('Las cantidades se sumarán o restarán del inventario y quedarán en el kardex. En un conteo, cada producto queda en lo contado. Un ajuste aplicado no se edita: un error se corrige con otro ajuste.')
            ->modalSubmitActionLabel('Sí, aplicar')
            ->action(function (AjusteInventario $record): void {
                self::aplicar($record);
            });
    }

    /**
     * Si el usuario puede aplicar ajustes (permiso Aplicar:AjusteInventario).
     */
    public static function puedeAplicar(): bool
    {
        $usuario = auth()->user();

        return $usuario instanceof User && $usuario->can('aplicar', AjusteInventario::class);
    }

    /**
     * Aplica y avisa cómo le fue. Si no se puede (falta existencia), el
     * ajuste se queda en borrador y se dice por qué.
     */
    public static function aplicar(AjusteInventario $ajuste): bool
    {
        try {
            app(AjusteInventarioService::class)->aplicar($ajuste);
        } catch (GrupoOlympoException $e) {
            Notification::make()
                ->danger()
                ->title('No se pudo aplicar el ajuste')
                ->body($e->getMessage().' Quedó como borrador.')
                ->persistent()
                ->send();

            return false;
        }

        $ajuste->refresh();

        Notification::make()
            ->success()
            ->title("Ajuste {$ajuste->numero} aplicado")
            ->body('El inventario ya está actualizado.')
            ->send();

        return true;
    }
}
