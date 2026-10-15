<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Otra unidad en que se compra o vende un producto, con su factor de
 * conversión a la unidad base: Caja x100, Rollo 100 m, Cubeta 5 gal.
 *
 * @property int $id
 * @property int $producto_id
 * @property int $unidad_medida_id
 * @property string|null $nombre
 * @property string $factor
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
        'codigo_barras',
        'precio_venta',
        'activo',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'activo' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor'       => 'decimal:4',
            'precio_venta' => 'decimal:2',
            'activo'       => 'boolean',
        ];
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
     * Etiqueta para selects y documentos: el nombre si lo tiene, o
     * "Caja (100 UND)" armado con la unidad y el factor.
     */
    public function etiqueta(): string
    {
        if ($this->nombre !== null && $this->nombre !== '') {
            return $this->nombre;
        }

        $base = $this->producto->unidadMedida->abreviatura;

        return $this->unidadMedida->nombre.' ('.cantidadFormato((float) $this->factor).' '.$base.')';
    }
}
