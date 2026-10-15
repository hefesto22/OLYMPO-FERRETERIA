<?php

declare(strict_types=1);

namespace App\Filament\Resources\RangosCai\Pages;

use App\Filament\Resources\RangosCai\RangoCaiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRangosCai extends ManageRecords
{
    protected static string $resource = RangoCaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar rango CAI'),
        ];
    }
}
