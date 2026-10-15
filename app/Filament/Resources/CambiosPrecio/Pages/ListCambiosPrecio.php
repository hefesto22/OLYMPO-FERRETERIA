<?php

declare(strict_types=1);

namespace App\Filament\Resources\CambiosPrecio\Pages;

use App\Enums\EstadoCambioPrecio;
use App\Filament\Resources\CambiosPrecio\CambioPrecioResource;
use App\Models\CambioPrecio;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListCambiosPrecio extends ListRecords
{
    protected static string $resource = CambioPrecioResource::class;

    public function getTabs(): array
    {
        return [
            'pendientes' => Tab::make('Por revisar')
                ->icon(Heroicon::OutlinedHandRaised)
                ->badge(fn (): ?int => CambioPrecio::query()->pendientes()->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('estado', EstadoCambioPrecio::Pendiente)),
            'historial' => Tab::make('Historial')
                ->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('estado', '!=', EstadoCambioPrecio::Pendiente)),
        ];
    }
}
