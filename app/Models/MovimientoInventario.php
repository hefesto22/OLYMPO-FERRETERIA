<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoMovimientoInventario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Fila del kardex. Inmutable: no se edita ni se borra; una corrección
 * se hace con otro movimiento (ajuste o anulación).
 *
 * @property int $id
 * @property int $producto_id
 * @property TipoMovimientoInventario $tipo
 * @property string $cantidad
 * @property string $stock_anterior
 * @property string $stock_nuevo
 * @property string $costo_unitario
 * @property string $costo_promedio
 * @property string|null $documento_type
 * @property int|null $documento_id
 * @property string|null $descripcion
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property-read Producto $producto
 * @property-read Model|null $documento
 * @property-read User|null $usuario
 */
class MovimientoInventario extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'movimientos_inventario';

    /** @var list<string> */
    protected $fillable = [
        'producto_id',
        'tipo',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'costo_unitario',
        'costo_promedio',
        'documento_type',
        'documento_id',
        'descripcion',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo'           => TipoMovimientoInventario::class,
            'cantidad'       => 'decimal:6',
            'stock_anterior' => 'decimal:6',
            'stock_nuevo'    => 'decimal:6',
            'costo_unitario' => 'decimal:4',
            'costo_promedio' => 'decimal:4',
            'documento_id'   => 'integer',
            'created_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // El kardex no se reescribe.
        static::updating(static fn (): bool => false);
        static::deleting(static fn (): bool => false);
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
     * Entrada, ajuste o venta que originó el movimiento.
     *
     * @return MorphTo<Model, $this>
     */
    public function documento(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function entrada(): ?float
    {
        return $this->tipo->esEntrada() ? (float) $this->cantidad : null;
    }

    public function salida(): ?float
    {
        return $this->tipo->esEntrada() ? null : (float) $this->cantidad;
    }
}
