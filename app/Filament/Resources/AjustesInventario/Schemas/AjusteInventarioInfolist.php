<?php

declare(strict_types=1);

namespace App\Filament\Resources\AjustesInventario\Schemas;

use App\Enums\EstadoDocumento;
use App\Enums\TipoAjuste;
use App\Models\AjusteInventario;
use App\Models\AjusteInventarioDetalle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Detalle de un ajuste (en un modal): qué pasó, quién y cuándo, y qué le
 * pasó a cada producto (cuánto cambió y de cuánto a cuánto quedó).
 */
class AjusteInventarioInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 2, 'md' => 4])
                    ->schema([
                        TextEntry::make('motivo')
                            ->label('Qué pasó')
                            ->icon(fn (AjusteInventario $record): Heroicon => $record->motivo->getIcon())
                            ->weight('medium'),
                        TextEntry::make('tipo')
                            ->label('Movimiento')
                            ->badge(),
                        TextEntry::make('estado')
                            ->label('Estado')
                            ->badge(),
                        TextEntry::make('fecha')
                            ->label('Fecha')
                            ->date('d/m/Y'),
                        TextEntry::make('createdBy.name')
                            ->label('Registró')
                            ->placeholder('—'),
                        TextEntry::make('aplicado_at')
                            ->label('Aplicado')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Todavía no'),
                        TextEntry::make('aplicadoPor.name')
                            ->label('Aplicó')
                            ->placeholder('—'),
                        TextEntry::make('notas')
                            ->label('Nota')
                            ->placeholder('—'),
                    ]),

                RepeatableEntry::make('detalles')
                    ->label('Productos')
                    ->table([
                        TableColumn::make('Producto'),
                        TableColumn::make('Cambio'),
                        TableColumn::make('De cuánto a cuánto'),
                        TableColumn::make('Costo'),
                    ])
                    ->schema([
                        TextEntry::make('producto.nombre'),
                        TextEntry::make('cambio')
                            ->state(fn (AjusteInventarioDetalle $record): string => self::cambio($record))
                            ->color(fn (AjusteInventarioDetalle $record): string => match (true) {
                                $record->cambio($record->ajuste->tipo) > 0 => 'success',
                                $record->cambio($record->ajuste->tipo) < 0 => 'danger',
                                default                                    => 'gray',
                            })
                            ->weight('medium'),
                        TextEntry::make('existencias')
                            ->state(fn (AjusteInventarioDetalle $record): string => self::existencias($record)),
                        TextEntry::make('costo_unitario')
                            ->state(fn (AjusteInventarioDetalle $record): string => self::costo($record)),
                    ]),
            ]);
    }

    /**
     * "+50 LB", "−5 LB" o "Sin cambio".
     */
    private static function cambio(AjusteInventarioDetalle $detalle): string
    {
        $cambio = $detalle->cambio($detalle->ajuste->tipo);

        if (abs($cambio) < 0.0000005) {
            return 'Sin cambio';
        }

        return ($cambio > 0 ? '+' : '−').cantidadFormato(abs($cambio)).' '.$detalle->producto->unidadMedida->abreviatura;
    }

    /**
     * Aplicado: "30 → 25 LB". En borrador: lo contado, o nada.
     */
    private static function existencias(AjusteInventarioDetalle $detalle): string
    {
        $unidad = $detalle->producto->unidadMedida->abreviatura;

        if ($detalle->ajuste->estado === EstadoDocumento::Confirmado && $detalle->existencia_antes !== null) {
            $antes = (float) $detalle->existencia_antes;

            return cantidadFormato($antes).' → '.cantidadFormato($antes + $detalle->cambio($detalle->ajuste->tipo)).' '.$unidad;
        }

        if ($detalle->contado !== null) {
            return 'Contado: '.cantidadFormato((float) $detalle->contado).' '.$unidad;
        }

        return '—';
    }

    private static function costo(AjusteInventarioDetalle $detalle): string
    {
        if ($detalle->tipoMovimiento($detalle->ajuste->tipo) !== TipoAjuste::Entrada) {
            return 'Costo promedio';
        }

        return (float) $detalle->costo_unitario > 0
            ? costoLempiras((float) $detalle->costo_unitario)
            : 'Costo promedio';
    }
}
