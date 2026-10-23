<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Tables;

use App\Enums\CategoriaGasto;
use App\Enums\TipoDocumentoCompra;
use App\Models\Compra;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ComprasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('numero_factura')
                    ->label('Documento')
                    ->searchable()
                    ->placeholder('Sin número')
                    ->description(fn (Compra $record): string => $record->tipo_documento->getLabel()),
                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->searchable()
                    ->placeholder('—')
                    ->description(fn (Compra $record): ?string => self::rtn($record))
                    ->wrap(),
                TextColumn::make('categoria')
                    ->label('En qué se gastó')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('isv')
                    ->label('ISV')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                    ->alignEnd()
                    ->summarize(Sum::make()->label('ISV')->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))
                    ->weight('bold')
                    ->sortable()
                    ->alignEnd()
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn (mixed $state): string => lempiras((float) $state))),
                TextColumn::make('numero')
                    ->label('N.º interno')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('createdBy.name')
                    ->label('Registró')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('fecha', 'desc')
            ->filters([
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, mixed $desde): Builder => $q->whereDate('fecha', '>=', $desde))
                        ->when($data['hasta'] ?? null, fn (Builder $q, mixed $hasta): Builder => $q->whereDate('fecha', '<=', $hasta))),
                SelectFilter::make('tipo_documento')
                    ->label('Documento')
                    ->options(TipoDocumentoCompra::class),
                SelectFilter::make('categoria')
                    ->label('En qué se gastó')
                    ->options(CategoriaGasto::class),
                SelectFilter::make('proveedor')
                    ->label('Proveedor')
                    ->relationship('proveedor', 'nombre')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make()
                    ->label('Borradas'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(fn (Compra $record): string => $record->tipo_documento->getLabel().' '.($record->numero_factura ?? $record->numero))
                    ->modalWidth(Width::FourExtraLarge)
                    ->after(function (Compra $record): void {
                        $record->guardarRtnEnProveedor();
                    }),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->emptyStateHeading('Todavía no hay compras')
            ->emptyStateDescription('Registra aquí las facturas y recibos de tus proveedores: mercadería, luz, alquiler, transporte. Los productos que entran al inventario van en Entradas de inventario.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutGlobalScopes([SoftDeletingScope::class])
                ->with(['proveedor', 'createdBy']));
    }

    /**
     * "RTN 08011999123456" o el aviso de que una factura no lo trae.
     */
    private static function rtn(Compra $record): ?string
    {
        if (filled($record->rtn_proveedor)) {
            return 'RTN '.$record->rtn_proveedor;
        }

        return $record->tipo_documento === TipoDocumentoCompra::Factura ? 'Sin RTN: su ISV no se descuenta' : null;
    }
}
