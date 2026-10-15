<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnosCaja\Pages;

use App\Filament\Resources\TurnosCaja\TurnoCajaResource;
use Filament\Resources\Pages\ListRecords;

class ListTurnosCaja extends ListRecords
{
    protected static string $resource = TurnoCajaResource::class;
}
