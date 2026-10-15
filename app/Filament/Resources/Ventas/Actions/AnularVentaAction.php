<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ventas\Actions;

use App\Domain\Exceptions\GrupoOlympoException;
use App\Models\User;
use App\Models\Venta;
use App\Services\Ventas\VentaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Anula una venta: devuelve al inventario lo vendido. Requiere el
 * permiso Anular:Venta (supervisor o administrador).
 */
final class AnularVentaAction
{
    public static function make(): Action
    {
        return Action::make('anular')
            ->label('Anular')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('anular')
            ->visible(fn (Venta $record): bool => ! $record->estaAnulada())
            ->requiresConfirmation()
            ->modalHeading(fn (Venta $record): string => "Anular la venta {$record->numero}")
            ->modalDescription('Los productos regresan al inventario. El número de factura queda usado (se conserva anulada para el SAR). Si el cliente ya se llevó el dinero de vuelta, regístralo como retiro de caja.')
            ->modalSubmitActionLabel('Anular venta')
            ->schema([
                Textarea::make('motivo')
                    ->label('Motivo de la anulación')
                    ->required()
                    ->maxLength(255)
                    ->rows(2),
            ])
            ->action(function (Venta $record, array $data): void {
                $usuario = Auth::user();

                if (! $usuario instanceof User) {
                    return;
                }

                try {
                    app(VentaService::class)->anular($record, (string) $data['motivo'], $usuario);
                } catch (GrupoOlympoException $e) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo anular la venta')
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->success()
                    ->title("Venta {$record->numero} anulada")
                    ->body('Los productos regresaron al inventario.')
                    ->send();
            });
    }
}
