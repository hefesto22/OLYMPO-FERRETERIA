<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Actions;

use App\Domain\Exceptions\GrupoOlympoException;
use App\Enums\EstadoDocumento;
use App\Models\Compra;
use App\Services\Inventario\CompraService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Anula una compra confirmada: saca del inventario lo que entró.
 */
final class AnularCompraAction
{
    public static function make(): Action
    {
        return Action::make('anular')
            ->label('Anular')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('anular')
            ->visible(fn (Compra $record): bool => $record->estado === EstadoDocumento::Confirmado)
            ->requiresConfirmation()
            ->modalHeading(fn (Compra $record): string => "Anular la compra {$record->numero}")
            ->modalDescription('Se restarán del inventario las cantidades que entraron con esta compra. Si parte de la mercadería ya se vendió, no se podrá anular.')
            ->modalSubmitActionLabel('Anular compra')
            ->schema([
                Textarea::make('motivo')
                    ->label('Motivo de la anulación')
                    ->required()
                    ->maxLength(255)
                    ->rows(2),
            ])
            ->action(function (Compra $record, array $data): void {
                try {
                    app(CompraService::class)->anular($record, (string) $data['motivo']);
                } catch (GrupoOlympoException $e) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo anular la compra')
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->success()
                    ->title("Compra {$record->numero} anulada")
                    ->send();
            });
    }
}
