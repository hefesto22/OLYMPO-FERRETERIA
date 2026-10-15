<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Pages;

use App\Filament\Resources\AjustesInventario\AjusteInventarioResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAjusteInventario extends EditRecord
{
    protected static string $resource = AjusteInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return AjusteInventarioResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
