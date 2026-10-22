<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoDescuento;
use App\Enums\TipoIsv;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Línea de una venta, con su desglose de ISV y el costo al momento de
 * vender (para calcular márgenes reales).
 *
 * @property int $id
 * @property int $venta_id
 * @property int $producto_id
 * @property int|null $producto_presentacion_id
 * @property string $descripcion
 * @property string $unidad
 * @property string $cantidad
 * @property string $factor
 * @property string $cantidad_base
 * @property string $precio_unitario
 * @property TipoDescuento $descuento_tipo
 * @property string $descuento_valor
 * @property string $descuento_pct
 * @property string $descuento_global
 * @property string $descuento
 * @property TipoIsv $tipo_isv
 * @property string $base_imponible
 * @property string $isv
 * @property string $total
 * @property string $costo_unitario_base
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Venta $venta
 * @property-read Producto $producto
 * @property-read ProductoPresentacion|null $presentacion
 */
class VentaDetalle extends Model
{
    protected $table = 'venta_detalles';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad'            => 'decimal:3',
            'factor'              => 'decimal:8',
            'cantidad_base'       => 'decimal:6',
            'precio_unitario'     => 'decimal:2',
            'descuento_tipo'      => TipoDescuento::class,
            'descuento_valor'     => 'decimal:2',
            'descuento_pct'       => 'decimal:2',
            'descuento_global'    => 'decimal:2',
            'descuento'           => 'decimal:2',
            'tipo_isv'            => TipoIsv::class,
            'base_imponible'      => 'decimal:2',
            'isv'                 => 'decimal:2',
            'total'               => 'decimal:2',
            'costo_unitario_base' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Venta, $this>
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        // Incluye eliminados: la venta debe seguir mostrando el producto.
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
     * Lo rebajado por el descuento de la propia línea, sin la parte que le
     * tocó del descuento a toda la venta.
     */
    public function descuentoDeLinea(): float
    {
        return round((float) $this->descuento - (float) $this->descuento_global, 2);
    }

    /**
     * Cómo se nombra en el ticket: "Descuento 5 %" o, si fue en lempiras,
     * solo "Descuento" (el monto va al lado).
     */
    public function etiquetaDescuento(): string
    {
        return $this->descuento_tipo === TipoDescuento::Monto
            ? 'Descuento'
            : 'Descuento '.cantidadFormato((float) $this->descuento_valor, 2).' %';
    }

    /**
     * Costo de lo vendido en esta línea (sin ISV).
     */
    public function costoTotal(): float
    {
        return round((float) $this->cantidad_base * (float) $this->costo_unitario_base, 2);
    }
}
