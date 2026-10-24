<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Pages;

use App\Filament\Resources\AjustesInventario\Actions\AplicarAjusteAction;
use App\Filament\Resources\AjustesInventario\AjusteInventarioResource;
use App\Models\AjusteInventario;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

/**
 * Lista de ajustes. Se hacen en un modal: "Aplicar ajuste" lo guarda y
 * lo aplica de una vez; "Guardar como borrador" lo deja para después.
 */
class ManageAjustesInventario extends ManageRecords
{
    protected static string $resource = AjusteInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo ajuste')
                ->modalHeading('Nuevo ajuste de inventario')
                ->modalDescription(fn (): string => self::quienYCuando())
                ->modalWidth(Width::FiveExtraLarge)
                ->createAnother(false)
                ->modalSubmitActionLabel(fn (): string => AplicarAjusteAction::puedeAplicar() ? 'Aplicar ajuste' : 'Guardar borrador')
                ->extraModalFooterActions(fn (CreateAction $action): array => AplicarAjusteAction::puedeAplicar()
                    ? [
                        $action->makeModalSubmitAction('borrador', arguments: ['borrador' => true])
                            ->label('Guardar como borrador')
                            ->color('gray'),
                    ]
                    : [])
                ->successNotification(null)
                ->after(function (AjusteInventario $record, array $arguments): void {
                    if (($arguments['borrador'] ?? false) || ! AplicarAjusteAction::puedeAplicar()) {
                        Notification::make()
                            ->success()
                            ->title("Ajuste {$record->numero} guardado como borrador")
                            ->body('Revísalo y aplícalo desde la lista cuando esté listo.')
                            ->send();

                        return;
                    }

                    AplicarAjusteAction::aplicar($record);
                }),
        ];
    }

    /**
     * "Hoy, 23 de octubre de 2026, lo registra Ana." La fecha y el usuario
     * los pone el sistema.
     */
    private static function quienYCuando(): string
    {
        $usuario = auth()->user();

        return 'Hoy, '.fechaLarga(now()).($usuario instanceof User ? ', lo registra '.$usuario->name : '').'.';
    }
}
