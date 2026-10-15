<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoIsv;
use App\Support\NumeroDocumento;
use App\Traits\HasAuditFields;
use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Producto de la ferretería.
 *
 * El stock se lleva SIEMPRE en la unidad base (unidad_medida_id). Las
 * cajas, rollos, cubetas o quintales son presentaciones con un factor
 * de conversión. `stock`, `costo_promedio` y `ultimo_costo` no son
 * fillable: solo los modifica InventarioService, dentro de una
 * transacción y dejando su movimiento en el kardex.
 *
 * @property int $id
 * @property string|null $codigo
 * @property string|null $codigo_barras
 * @property string $nombre
 * @property string|null $descripcion
 * @property int $categoria_id
 * @property int|null $marca_id
 * @property int $unidad_medida_id
 * @property TipoIsv $tipo_isv
 * @property string $precio_venta
 * @property string|null $precio_mayoreo
 * @property string $costo_promedio
 * @property string $ultimo_costo
 * @property string $stock
 * @property string $stock_minimo
 * @property string|null $ubicacion
 * @property string|null $imagen_path
 * @property bool $controla_stock
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Categoria|null $categoria
 * @property-read Marca|null $marca
 * @property-read UnidadMedida $unidadMedida
 * @property-read Collection<int, ProductoPresentacion> $presentaciones
 */
class Producto extends Model
{
    use HasAuditFields;

    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    protected $table = 'productos';

    /** @var list<string> */
    protected $fillable = [
        'codigo',
        'codigo_barras',
        'nombre',
        'descripcion',
        'categoria_id',
        'marca_id',
        'unidad_medida_id',
        'tipo_isv',
        'precio_venta',
        'precio_mayoreo',
        'stock_minimo',
        'ubicacion',
        'imagen_path',
        'controla_stock',
        'activo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'tipo_isv'       => 'gravado_15',
        'stock'          => 0,
        'stock_minimo'   => 0,
        'costo_promedio' => 0,
        'ultimo_costo'   => 0,
        'controla_stock' => true,
        'activo'         => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_isv'       => TipoIsv::class,
            'precio_venta'   => 'decimal:2',
            'precio_mayoreo' => 'decimal:2',
            'costo_promedio' => 'decimal:4',
            'ultimo_costo'   => 'decimal:4',
            'stock'          => 'decimal:3',
            'stock_minimo'   => 'decimal:3',
            'controla_stock' => 'boolean',
            'activo'         => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Código automático P-000123 cuando no se teclea uno.
        static::created(static function (Producto $producto): void {
            if ($producto->codigo !== null && $producto->codigo !== '') {
                return;
            }

            $prefijo = (string) config('ferreteria.documentos.prefijo_producto', 'P');
            $base = NumeroDocumento::formatear($prefijo, $producto->id);
            $codigo = $base;
            $intento = 1;

            // Si ese código ya lo tecleó alguien a mano en otro producto.
            while (self::withTrashed()->where('codigo', $codigo)->exists()) {
                $codigo = $base.'-'.$intento;
                $intento++;
            }

            $producto->forceFill(['codigo' => $codigo])->saveQuietly();
        });
    }

    /**
     * Auditoría: quién cambió precios, impuesto o estado del producto.
     * El stock NO se registra aquí — su historia completa está en el kardex.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['codigo', 'codigo_barras', 'nombre', 'precio_venta', 'precio_mayoreo', 'tipo_isv', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Producto {$eventName}");
    }

    // ─── Relaciones ───────────────────────────────────────────

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    /**
     * @return BelongsTo<Marca, $this>
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    /**
     * @return BelongsTo<UnidadMedida, $this>
     */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    /**
     * @return HasMany<ProductoPresentacion, $this>
     */
    public function presentaciones(): HasMany
    {
        return $this->hasMany(ProductoPresentacion::class, 'producto_id');
    }

    /**
     * @return HasMany<MovimientoInventario, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'producto_id');
    }

    // ─── Reglas del producto ──────────────────────────────────

    public function existencia(): float
    {
        return (float) $this->stock;
    }

    public function estaBajoMinimo(): bool
    {
        return $this->controla_stock && (float) $this->stock <= (float) $this->stock_minimo;
    }

    /**
     * Precio de venta sin ISV (el precio se guarda con ISV incluido).
     */
    public function precioSinIsv(): float
    {
        return round((float) $this->precio_venta / (1 + $this->tipo_isv->tasa()), 2);
    }

    /**
     * Margen sobre el precio sin ISV, como fracción (0.30 = 30%).
     * Null si todavía no hay costo o precio.
     */
    public function margen(): ?float
    {
        $precio = $this->precioSinIsv();
        $costo = (float) $this->costo_promedio;

        if ($precio <= 0 || $costo <= 0) {
            return null;
        }

        return round(($precio - $costo) / $precio, 4);
    }

    // ─── Scopes ───────────────────────────────────────────────

    /**
     * @param Builder<Producto> $query
     *
     * @return Builder<Producto>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Productos con control de stock cuya existencia está en o bajo el mínimo.
     *
     * @param Builder<Producto> $query
     *
     * @return Builder<Producto>
     */
    public function scopeBajoMinimo(Builder $query): Builder
    {
        return $query
            ->where('controla_stock', true)
            ->where('activo', true)
            ->whereColumn('stock', '<=', 'stock_minimo');
    }
}
