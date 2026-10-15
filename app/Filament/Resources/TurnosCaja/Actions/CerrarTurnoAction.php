<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnosCaja\Actions;

use App\Domain\Exceptions\GrupoOlympoException;
use App\Filament\Schemas\Components\MontoField;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Services\Ventas\TurnoCajaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Cierra un turno desde el listado de turnos. El cajero puede cerrar el
 * suyo; para cerrar el de otro hace falta además Update:TurnoCaja
 * (supervisor), p. ej. si el cajero se fue sin cerrar.
 */
final class CerrarTurnoAction
{
    public static function make(): Action
    {
        return Action::make('cerrar')
            ->label('Cerrar turno')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->authorize('cerrar')
            ->visible(fn (TurnoCaja $record): bool => $record->estaAbierto() && self::puedeCerrar($record))
            ->modalHeading(fn (TurnoCaja $record): string => "Cerrar el turno de {$record->cajero->name} ({$record->caja->nombre})")
            ->modalDescription(fn (TurnoCaja $record): string => 'Efectivo que debe haber en caja: '.lempiras(app(TurnoCajaService::class)->resumen($record)['efectivo_esperado']).'.')
            ->modalSubmitActionLabel('Cerrar turno')
            ->schema([
                MontoField::make('efectivo_contado', 'Efectivo contado en caja'),
                Textarea::make('notas')
                    ->label('Notas del cierre')
                    ->rows(2),
            ])
            ->action(function (TurnoCaja $record, array $data): void {
                $usuario = Auth::user();

                if (! $usuario instanceof User) {
                    return;
                }

                try {
                    app(TurnoCajaService::class)->cerrar(
                        $record,
                        (float) $data['efectivo_contado'],
                        isset($data['notas']) ? (string) $data['notas'] : null,
                        $usuario,
                    );
                } catch (GrupoOlympoException $e) {
                    Notification::make()->danger()->title('No se pudo cerrar el turno')->body($e->getMessage())->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->success()
                    ->title('Turno cerrado')
                    ->body('Diferencia: '.lempiras((float) $record->diferencia))
                    ->actions([
                        Action::make('imprimir')
                            ->label('Imprimir cierre')
                            ->url(route('filament.admin.tickets.turno', ['turno' => $record]))
                            ->openUrlInNewTab(),
                    ])
                    ->send();
            });
    }

    private static function puedeCerrar(TurnoCaja $turno): bool
    {
        $usuario = Auth::user();

        return $usuario instanceof User
            && ($turno->user_id === $usuario->id || $usuario->can('update', $turno));
    }
}
