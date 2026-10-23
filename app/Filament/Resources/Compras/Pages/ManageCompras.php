<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Pages;

use App\Filament\Resources\Compras\CompraResource;
use App\Filament\Resources\Compras\Widgets\ResumenComprasMes;
use App\Models\Compra;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

/**
 * Lista de compras con el resumen del mes. Registrar y editar se hacen
 * en un modal, sin salir de la lista.
 */
class ManageCompras extends ManageRecords
{
    protected static string $resource = CompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar compra')
                ->modalHeading('Registrar compra')
                ->modalWidth(Width::FourExtraLarge)
                ->modalSubmitActionLabel('Guardar')
                ->createAnotherAction(fn (Action $action): Action => $action->label('Guardar y registrar otra'))
                ->successNotificationTitle('Compra registrada')
                ->after(function (Compra $record): void {
                    $record->guardarRtnEnProveedor();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ResumenComprasMes::class,
        ];
    }
}
