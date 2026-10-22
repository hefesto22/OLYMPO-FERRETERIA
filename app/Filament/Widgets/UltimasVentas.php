<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Ventas\VentaResource;
use App\Models\User;
use App\Models\Venta;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Las últimas ventas, para ver de un vistazo qué se está vendiendo.
 */
class UltimasVentas extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Últimas ventas';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can('viewAny', Venta::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Venta::query()->latest('created_at')->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('numero')
                    ->label('Número')
                    ->weight('medium')
                    ->description(fn (Venta $record): string => $record->tipo_documento->getLabel()),
                TextColumn::make('cliente_nombre')
                    ->label('Cliente'),
                TextColumn::make('created_at')
                    ->label('Hora')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i'),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                    ->weight('semibold')
                    ->alignEnd(),
            ])
            ->recordUrl(fn (Venta $record): string => VentaResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Todavía no hay ventas')
            ->emptyStateDescription('Las ventas que se cobren en el punto de venta aparecen aquí.');
    }
}
