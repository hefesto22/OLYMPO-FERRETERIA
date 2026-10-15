<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoCambioPrecio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un cambio de precio provocado por el costo de un producto con margen:
 * las subidas automáticas (historial) y las bajas que esperan al dueño.
 * Lo crea y lo resuelve PrecioService; nadie lo edita a mano.
 *
 * @property int $id
 * @property int $producto_id
 * @property EstadoCambioPrecio $estado
 * @property string $costo_anterior
 * @property string $costo_nuevo
 * @property string $precio_anterior
 * @property string $precio_nuevo
 * @property string|null $mayoreo_anterior
 * @property string|null $mayoreo_nuevo
 * @property list<array{etiqueta: string, antes: float, despues: float}>|null $detalle
 * @property string|null $origen
 * @property int|null $revisado_por
 * @property Carbon|null $revisado_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Producto $producto
 * @property-read User|null $revisor
 */
class CambioPrecio extends Model
{
    protected $table = 'cambios_precio';

    /** @var list<string> */
    protected $fillable = [
        'producto_id',
        'estado',
        'costo_anterior',
        'costo_nuevo',
        'precio_anterior',
        'precio_nuevo',
        'mayoreo_anterior',
        'mayoreo_nuevo',
        'detalle',
        'origen',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado'           => EstadoCambioPrecio::class,
            'costo_anterior'   => 'decimal:4',
            'costo_nuevo'      => 'decimal:4',
            'precio_anterior'  => 'decimal:2',
            'precio_nuevo'     => 'decimal:2',
            'mayoreo_anterior' => 'decimal:2',
            'mayoreo_nuevo'    => 'decimal:2',
            'detalle'          => 'array',
            'revisado_at'      => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        // Incluye eliminados: el historial debe seguir mostrando el producto.
        return $this->belongsTo(Producto::class, 'producto_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    /**
     * @param Builder<CambioPrecio> $query
     *
     * @return Builder<CambioPrecio>
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoCambioPrecio::Pendiente);
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoCambioPrecio::Pendiente;
    }

    /**
     * Cambios en presentaciones con precio automático, en texto:
     * ["Bolsa 1 lb: L. 29.00 → L. 28.00"].
     *
     * @return list<string>
     */
    public function lineasDetalle(): array
    {
        $lineas = [];

        foreach ($this->detalle ?? [] as $linea) {
            $lineas[] = $linea['etiqueta'].': '.lempiras($linea['antes']).' → '.lempiras($linea['despues']);
        }

        return $lineas;
    }
}
