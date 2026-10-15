<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Pages;

use App\Filament\Resources\Compras\Actions\AnularCompraAction;
use App\Filament\Resources\Compras\Actions\ConfirmarCompraAction;
use App\Filament\Resources\Compras\CompraResource;
use App\Models\Compra;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCompra extends ViewRecord
{
    protected static string $resource = CompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConfirmarCompraAction::make(),
            EditAction::make()
                ->visible(fn (Compra $record): bool => $record->esBorrador()),
            AnularCompraAction::make(),
        ];
    }
}
