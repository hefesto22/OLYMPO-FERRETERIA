<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ajuste_inventario_id
 * @property int $producto_id
 * @property string $cantidad
 * @property string $costo_unitario
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AjusteInventario $ajuste
 * @property-read Producto $producto
 */
class AjusteInventarioDetalle extends Model
{
    protected $table = 'ajuste_inventario_detalles';

    /** @var list<string> */
    protected $fillable = [
        'ajuste_inventario_id',
        'producto_id',
        'cantidad',
        'costo_unitario',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'costo_unitario' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad'       => 'decimal:3',
            'costo_unitario' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<AjusteInventario, $this>
     */
    public function ajuste(): BelongsTo
    {
        return $this->belongsTo(AjusteInventario::class, 'ajuste_inventario_id');
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        // Incluye eliminados: el historial debe seguir mostrando el producto.
        return $this->belongsTo(Producto::class, 'producto_id')->withTrashed();
    }
}
