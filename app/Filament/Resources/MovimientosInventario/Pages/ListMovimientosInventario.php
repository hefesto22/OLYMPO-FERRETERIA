<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovimientosInventario\Pages;

use App\Filament\Resources\MovimientosInventario\MovimientoInventarioResource;
use Filament\Resources\Pages\ListRecords;

class ListMovimientosInventario extends ListRecords
{
    protected static string $resource = MovimientoInventarioResource::class;
}
