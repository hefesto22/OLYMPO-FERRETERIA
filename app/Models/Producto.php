<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoCambioPrecio;
use App\Enums\TipoIsv;
use App\Enums\TipoMargen;
use App\Support\NumeroDocumento;
use App\Support\Precios\CalculadoraPrecio;
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
 * Precio: a mano, o por margen sobre el costo (TipoMargen). Con margen,
 * `precio_venta` y `precio_mayoreo` se calculan solos sobre
 * `costo_precio`, que solo mueve PrecioService (sube solo si sube el
 * costo; si baja, espera al dueño).
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
 * @property TipoMargen $margen_tipo
 * @property string|null $margen_publico
 * @property string|null $margen_mayoreo
 * @property string $costo_promedio
 * @property string $ultimo_costo
 * @property string $costo_precio
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
 * @property-read Collection<int, CambioPrecio> $cambiosPrecio
 */
class Producto extends Model
{
    use HasAuditFields;

    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    /** Campos que, al cambiar, son una decisión de precio del dueño. */
    private const CAMPOS_DE_PRECIO = ['margen_tipo', 'margen_publico', 'margen_mayoreo', 'tipo_isv'];

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
        'margen_tipo',
        'margen_publico',
        'margen_mayoreo',
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
        'margen_tipo'    => 'manual',
        'stock'          => 0,
        'stock_minimo'   => 0,
        'costo_promedio' => 0,
        'ultimo_costo'   => 0,
        'costo_precio'   => 0,
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
            'margen_tipo'    => TipoMargen::class,
            'margen_publico' => 'decimal:2',
            'margen_mayoreo' => 'decimal:2',
            'costo_promedio' => 'decimal:4',
            'ultimo_costo'   => 'decimal:4',
            'costo_precio'   => 'decimal:4',
            'stock'          => 'decimal:6',
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

        // Con margen el precio no se teclea: sale del costo. Si se cambia el
        // margen o el ISV, es una decisión de precio nueva y se toma el costo
        // promedio de hoy; si solo se movió el costo base (PrecioService),
        // se recalcula sobre ese.
        static::saving(static function (Producto $producto): void {
            if (! $producto->usaMargen()) {
                return;
            }

            if ($producto->isDirty(self::CAMPOS_DE_PRECIO)) {
                $producto->forceFill(['costo_precio' => $producto->costo_promedio]);
            }

            if ($producto->isDirty([...self::CAMPOS_DE_PRECIO, 'costo_precio'])) {
                $producto->calcularPreciosConMargen();
            }
        });

        // Si el dueño fijó un margen nuevo, una baja que esperaba revisión ya no aplica.
        static::updated(static function (Producto $producto): void {
            if ($producto->wasChanged(self::CAMPOS_DE_PRECIO)) {
                $producto->cambiosPrecio()
                    ->where('estado', EstadoCambioPrecio::Pendiente)
                    ->update(['estado' => EstadoCambioPrecio::Descartado]);
            }
        });
    }

    /**
     * Auditoría: quién cambió precios, impuesto o estado del producto.
     * El stock NO se registra aquí — su historia completa está en el kardex.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['codigo', 'codigo_barras', 'nombre', 'precio_venta', 'precio_mayoreo', 'margen_tipo', 'margen_publico', 'margen_mayoreo', 'tipo_isv', 'activo'])
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

    /**
     * @return HasMany<CambioPrecio, $this>
     */
    public function cambiosPrecio(): HasMany
    {
        return $this->hasMany(CambioPrecio::class, 'producto_id');
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
     * Ganancia sobre el costo promedio, como fracción (0.30 = 30%): lo
     * mismo que se configura como margen. Null si aún no hay costo o precio.
     */
    public function gananciaSobreCosto(): ?float
    {
        $precio = $this->precioSinIsv();
        $costo = (float) $this->costo_promedio;

        if ($precio <= 0 || $costo <= 0) {
            return null;
        }

        return round(($precio - $costo) / $costo, 4);
    }

    public function usaMargen(): bool
    {
        return $this->margen_tipo !== TipoMargen::Manual;
    }

    /**
     * Precio con margen (ISV incluido, lempiras enteros) de `$factor`
     * unidades base al costo indicado. Null si el producto no usa margen o
     * no tiene margen para esa lista (mayoreo vacío).
     */
    public function precioConMargen(float $costoBase, bool $mayoreo = false, float $factor = 1.0): ?float
    {
        $margen = $mayoreo ? $this->margen_mayoreo : $this->margen_publico;

        if (! $this->usaMargen() || $margen === null) {
            return null;
        }

        return CalculadoraPrecio::precio($costoBase, $this->margen_tipo, (float) $margen, $this->tipo_isv->tasa(), $factor);
    }

    /**
     * Fija precio público y mayoreo con el margen sobre `costo_precio`.
     * Sin costo todavía, el precio queda en 0 (no se puede vender) hasta
     * la primera compra o existencia inicial.
     */
    public function calcularPreciosConMargen(): void
    {
        $costo = (float) $this->costo_precio;
        $mayoreo = $this->precioConMargen($costo, mayoreo: true);

        $this->forceFill([
            'precio_venta'   => $this->precioConMargen($costo) ?? 0.0,
            'precio_mayoreo' => $mayoreo !== null && $mayoreo > 0 ? $mayoreo : null,
        ]);
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
