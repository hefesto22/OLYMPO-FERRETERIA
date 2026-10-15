<?php

declare(strict_types=1);

namespace App\Filament\Resources\CambiosPrecio\Tables;

use App\Domain\Exceptions\GrupoOlympoException;
use App\Enums\EstadoCambioPrecio;
use App\Models\CambioPrecio;
use App\Models\User;
use App\Services\Precios\PrecioService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CambiosPrecioTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['producto', 'revisor']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (CambioPrecio $record): string => $record->origen ?? ''),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('costo_nuevo')
                    ->label('Costo (sin ISV)')
                    ->state(fn (CambioPrecio $record): string => lempiras((float) $record->costo_anterior).' → '.lempiras((float) $record->costo_nuevo)),
                TextColumn::make('precio_nuevo')
                    ->label('Precio público')
                    ->state(fn (CambioPrecio $record): string => lempiras((float) $record->precio_anterior).' → '.lempiras((float) $record->precio_nuevo))
                    ->color(fn (CambioPrecio $record): string => (float) $record->precio_nuevo < (float) $record->precio_anterior ? 'success' : 'danger')
                    ->weight('bold'),
                TextColumn::make('mayoreo_nuevo')
                    ->label('Mayoreo')
                    ->state(fn (CambioPrecio $record): ?string => $record->mayoreo_nuevo !== null
                        ? lempiras((float) $record->mayoreo_anterior).' → '.lempiras((float) $record->mayoreo_nuevo)
                        : null)
                    ->placeholder('—'),
                TextColumn::make('detalle')
                    ->label('Presentaciones')
                    ->state(fn (CambioPrecio $record): array => $record->lineasDetalle())
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('revisor.name')
                    ->label('Revisó')
                    ->description(fn (CambioPrecio $record): string => $record->revisado_at?->format('d/m/Y H:i') ?? '')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoCambioPrecio::class),
            ])
            ->recordActions([
                self::accionBajar(),
                self::accionMantener(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bajarSeleccionados')
                        ->label('Bajar precios')
                        ->icon(Heroicon::OutlinedArrowTrendingDown)
                        ->color('success')
                        ->authorize('aprobar')
                        ->requiresConfirmation()
                        ->modalDescription('Los productos seleccionados que estén por revisar pasan al precio nuevo.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            self::resolver($records, true);
                        }),
                    BulkAction::make('mantenerSeleccionados')
                        ->label('Mantener precios')
                        ->icon(Heroicon::OutlinedLockClosed)
                        ->color('gray')
                        ->authorize('aprobar')
                        ->requiresConfirmation()
                        ->modalDescription('Los productos seleccionados que estén por revisar conservan su precio actual.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            self::resolver($records, false);
                        }),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading('Nada por revisar')
            ->emptyStateDescription('Cuando una compra baje el costo de un producto con margen, aparecerá aquí para que decidas si le bajas el precio.');
    }

    private static function accionBajar(): Action
    {
        return Action::make('bajar')
            ->label('Bajar precio')
            ->icon(Heroicon::OutlinedArrowTrendingDown)
            ->color('success')
            ->authorize('aprobar')
            ->visible(fn (CambioPrecio $record): bool => $record->estaPendiente())
            ->requiresConfirmation()
            ->modalHeading(fn (CambioPrecio $record): string => "Bajar el precio de {$record->producto->nombre}")
            ->modalDescription(fn (CambioPrecio $record): string => self::resumen($record))
            ->modalSubmitActionLabel('Bajar precio')
            ->action(function (CambioPrecio $record): void {
                self::resolver([$record], true);
            });
    }

    private static function accionMantener(): Action
    {
        return Action::make('mantener')
            ->label('Mantener')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('gray')
            ->authorize('aprobar')
            ->visible(fn (CambioPrecio $record): bool => $record->estaPendiente())
            ->requiresConfirmation()
            ->modalHeading(fn (CambioPrecio $record): string => "Mantener el precio de {$record->producto->nombre}")
            ->modalDescription('El precio se queda como está. No se vuelve a preguntar por este costo; solo si baja todavía más.')
            ->modalSubmitActionLabel('Mantener precio')
            ->action(function (CambioPrecio $record): void {
                self::resolver([$record], false);
            });
    }

    private static function resumen(CambioPrecio $record): string
    {
        $texto = 'Precio público: '.lempiras((float) $record->precio_anterior).' → '.lempiras((float) $record->precio_nuevo).'.';

        if ($record->mayoreo_nuevo !== null) {
            $texto .= ' Mayoreo: '.lempiras((float) $record->mayoreo_anterior).' → '.lempiras((float) $record->mayoreo_nuevo).'.';
        }

        $presentaciones = $record->lineasDetalle();

        if ($presentaciones !== []) {
            $texto .= ' '.implode('. ', $presentaciones).'.';
        }

        return $texto;
    }

    /**
     * @param iterable<mixed> $cambios
     */
    private static function resolver(iterable $cambios, bool $bajar): void
    {
        $usuario = Auth::user();

        if (! $usuario instanceof User) {
            return;
        }

        $servicio = app(PrecioService::class);
        $hechos = 0;
        $errores = [];

        foreach ($cambios as $cambio) {
            if (! $cambio instanceof CambioPrecio || ! $cambio->estaPendiente()) {
                continue;
            }

            try {
                if ($bajar) {
                    $servicio->aprobar($cambio, $usuario);
                } else {
                    $servicio->rechazar($cambio, $usuario);
                }

                $hechos++;
            } catch (GrupoOlympoException $e) {
                $errores[] = $e->getMessage();
            }
        }

        if ($errores !== []) {
            Notification::make()
                ->danger()
                ->title('Algunos precios no se pudieron cambiar')
                ->body(implode(' ', $errores))
                ->persistent()
                ->send();
        }

        if ($hechos === 0) {
            return;
        }

        Notification::make()
            ->success()
            ->title($bajar
                ? ($hechos === 1 ? 'Precio bajado' : "{$hechos} precios bajados")
                : ($hechos === 1 ? 'Precio mantenido' : "{$hechos} precios mantenidos"))
            ->send();
    }
}
