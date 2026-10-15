<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EquivalenciaPresentacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Otra unidad en que se compra o vende un producto, con su factor de
 * conversión a la unidad base: Caja x100, Rollo 100 m, Cubeta 5 gal.
 *
 * También sirve para vender MENOS que la unidad base: un clavo suelto de
 * un producto que se lleva en libras se captura como "100 por LB" y su
 * factor queda en 0.01 (cada clavo descuenta 0.01 lb del inventario).
 *
 * El usuario teclea `factor_cantidad` y `factor_modo`; el `factor` se
 * calcula al guardar.
 *
 * @property int $id
 * @property int $producto_id
 * @property int $unidad_medida_id
 * @property string|null $nombre
 * @property string $factor
 * @property EquivalenciaPresentacion $factor_modo
 * @property string|null $factor_cantidad
 * @property string|null $codigo_barras
 * @property string|null $precio_venta
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Producto $producto
 * @property-read UnidadMedida $unidadMedida
 */
class ProductoPresentacion extends Model
{
    protected $table = 'producto_presentaciones';

    /** @var list<string> */
    protected $fillable = [
        'producto_id',
        'unidad_medida_id',
        'nombre',
        'factor',
        'factor_modo',
        'factor_cantidad',
        'codigo_barras',
        'precio_venta',
        'activo',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'factor_modo' => 'contiene',
        'activo'      => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor'          => 'decimal:8',
            'factor_modo'     => EquivalenciaPresentacion::class,
            'factor_cantidad' => 'decimal:4',
            'precio_venta'    => 'decimal:2',
            'activo'          => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (ProductoPresentacion $presentacion): void {
            $presentacion->sincronizarFactor();
        });
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /**
     * @return BelongsTo<UnidadMedida, $this>
     */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    /**
     * Calcula el factor con lo tecleado ("100 por LB" → 0.01). Si solo
     * llegó el factor (seeders, código anterior), lo tecleado es el factor.
     */
    public function sincronizarFactor(): void
    {
        $soloFactor = $this->isDirty('factor') && ! $this->isDirty(['factor_cantidad', 'factor_modo']);

        if ($this->factor_cantidad === null || $soloFactor) {
            $factor = (float) $this->factor;

            $this->forceFill([
                'factor_cantidad' => $this->factor_modo === EquivalenciaPresentacion::PorUnidadBase && $factor > 0
                    ? round(1 / $factor, 4)
                    : $factor,
            ]);

            return;
        }

        $this->forceFill([
            'factor' => $this->factor_modo->factor((float) $this->factor_cantidad),
        ]);
    }

    /**
     * Etiqueta para selects y documentos: el nombre si lo tiene, o
     * "Caja (100 UND)" / "Unidad (100 por LB)".
     */
    public function etiqueta(): string
    {
        if ($this->nombre !== null && $this->nombre !== '') {
            return $this->nombre;
        }

        return $this->unidadMedida->nombre.' ('.$this->equivalencia().')';
    }

    /**
     * "50 LB" o "100 por LB", según cómo se capturó.
     */
    public function equivalencia(): string
    {
        $base = $this->producto->unidadMedida->abreviatura;

        if ($this->factor_modo === EquivalenciaPresentacion::PorUnidadBase) {
            $cantidad = $this->factor_cantidad !== null
                ? (float) $this->factor_cantidad
                : ((float) $this->factor > 0 ? 1 / (float) $this->factor : 0.0);

            return cantidadFormato($cantidad, 2).' por '.$base;
        }

        return cantidadFormato((float) $this->factor).' '.$base;
    }
}
