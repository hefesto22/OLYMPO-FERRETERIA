<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntradasInventario\Actions;

use App\Domain\Exceptions\GrupoOlympoException;
use App\Enums\EstadoDocumento;
use App\Models\EntradaInventario;
use App\Services\Inventario\EntradaInventarioService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Anula una entrada confirmada: saca del inventario lo que entró.
 */
final class AnularEntradaAction
{
    public static function make(): Action
    {
        return Action::make('anular')
            ->label('Anular')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('anular')
            ->visible(fn (EntradaInventario $record): bool => $record->estado === EstadoDocumento::Confirmado)
            ->requiresConfirmation()
            ->modalHeading(fn (EntradaInventario $record): string => "Anular la entrada {$record->numero}")
            ->modalDescription('Se restarán del inventario las cantidades que entraron con esta entrada. Si parte de la mercadería ya se vendió, no se podrá anular.')
            ->modalSubmitActionLabel('Anular entrada')
            ->schema([
                Textarea::make('motivo')
                    ->label('Motivo de la anulación')
                    ->required()
                    ->maxLength(255)
                    ->rows(2),
            ])
            ->action(function (EntradaInventario $record, array $data): void {
                try {
                    app(EntradaInventarioService::class)->anular($record, (string) $data['motivo']);
                } catch (GrupoOlympoException $e) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo anular la entrada')
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->success()
                    ->title("Entrada {$record->numero} anulada")
                    ->send();
            });
    }
}
