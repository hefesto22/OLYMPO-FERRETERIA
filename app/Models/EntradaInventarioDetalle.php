<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoIsv;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Línea de una entrada de inventario. El formulario solo captura
 * producto, presentación, cantidad y costo neto (sin ISV); el resto
 * (factor, cantidad en unidad base, ISV, totales) se calcula aquí al
 * guardar, para que ninguna pantalla pueda dejar importes inconsistentes.
 *
 * @property int $id
 * @property int $entrada_inventario_id
 * @property int $producto_id
 * @property int|null $producto_presentacion_id
 * @property string $cantidad
 * @property string $factor
 * @property string $cantidad_base
 * @property string $costo_unitario
 * @property string $costo_unitario_base
 * @property TipoIsv $tipo_isv
 * @property string $subtotal
 * @property string $isv
 * @property string $total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EntradaInventario $entrada
 * @property-read Producto $producto
 * @property-read ProductoPresentacion|null $presentacion
 */
class EntradaInventarioDetalle extends Model
{
    protected $table = 'entrada_inventario_detalles';

    /** @var list<string> */
    protected $fillable = [
        'entrada_inventario_id',
        'producto_id',
        'producto_presentacion_id',
        'cantidad',
        'costo_unitario',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad'            => 'decimal:3',
            'factor'              => 'decimal:8',
            'cantidad_base'       => 'decimal:6',
            'costo_unitario'      => 'decimal:4',
            'costo_unitario_base' => 'decimal:4',
            'tipo_isv'            => TipoIsv::class,
            'subtotal'            => 'decimal:2',
            'isv'                 => 'decimal:2',
            'total'               => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (EntradaInventarioDetalle $detalle): void {
            $detalle->calcularImportes();
        });
    }

    /**
     * @return BelongsTo<EntradaInventario, $this>
     */
    public function entrada(): BelongsTo
    {
        return $this->belongsTo(EntradaInventario::class, 'entrada_inventario_id');
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
     * @return BelongsTo<ProductoPresentacion, $this>
     */
    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(ProductoPresentacion::class, 'producto_presentacion_id');
    }

    /**
     * Calcula factor, cantidad base, costo base, ISV y totales a partir
     * de lo capturado. Una presentación que no sea del producto se ignora
     * (se toma la unidad base).
     */
    public function calcularImportes(): void
    {
        $producto = Producto::withTrashed()->findOrFail($this->producto_id);

        $presentacion = $this->producto_presentacion_id !== null
            ? ProductoPresentacion::query()
                ->where('producto_id', $producto->id)
                ->find($this->producto_presentacion_id)
            : null;

        $factor = $presentacion !== null ? (float) $presentacion->factor : 1.0;
        $cantidad = (float) $this->cantidad;
        $costo = (float) $this->costo_unitario;

        $subtotal = round($cantidad * $costo, 2);
        $isv = round($subtotal * $producto->tipo_isv->tasa(), 2);

        $this->forceFill([
            'producto_presentacion_id' => $presentacion?->id,
            'factor'                   => $factor,
            'cantidad_base'            => round($cantidad * $factor, 6),
            'costo_unitario_base'      => round($costo / $factor, 4),
            'tipo_isv'                 => $producto->tipo_isv,
            'subtotal'                 => $subtotal,
            'isv'                      => $isv,
            'total'                    => round($subtotal + $isv, 2),
        ]);
    }

    /**
     * "Caja x100" o el nombre de la unidad base.
     */
    public function etiquetaUnidad(): string
    {
        return $this->presentacion?->etiqueta() ?? $this->producto->unidadMedida->nombre;
    }
}
