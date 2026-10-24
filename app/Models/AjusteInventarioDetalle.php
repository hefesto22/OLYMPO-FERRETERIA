<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoDocumento;
use App\Enums\TipoAjuste;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Línea de un ajuste. En una entrada o salida se escribe la cantidad; en
 * un conteo físico se escribe lo contado y la cantidad (la diferencia) y
 * su tipo se calculan aquí contra la existencia.
 *
 * El costo va sin ISV: en un borrador, el tecleado en una entrada (0 =
 * el promedio); al aplicar, el costo con que se movió el inventario.
 *
 * @property int $id
 * @property int $ajuste_inventario_id
 * @property int $producto_id
 * @property TipoAjuste|null $tipo
 * @property string $cantidad
 * @property string|null $contado
 * @property string|null $existencia_antes
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
        'contado',
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
            'tipo'             => TipoAjuste::class,
            'cantidad'         => 'decimal:6',
            'contado'          => 'decimal:6',
            'existencia_antes' => 'decimal:6',
            'costo_unitario'   => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (AjusteInventarioDetalle $detalle): void {
            if ($detalle->contado !== null) {
                $detalle->calcularConteo();

                return;
            }

            $detalle->setAttribute('tipo', null);
        });
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

    /**
     * Conteo físico: la diferencia entre lo contado y la existencia (la de
     * ahora o la que se pase). Sobra → entrada; falta → salida; igual → sin
     * tipo y cantidad 0.
     */
    public function calcularConteo(?float $existencia = null): void
    {
        $existencia ??= (float) Producto::withTrashed()->whereKey($this->producto_id)->value('stock');
        $diferencia = round((float) $this->contado - $existencia, 6);

        $this->forceFill([
            'existencia_antes' => $existencia,
            'cantidad'         => abs($diferencia),
            'tipo'             => match (true) {
                $diferencia > 0 => TipoAjuste::Entrada,
                $diferencia < 0 => TipoAjuste::Salida,
                default         => null,
            },
        ]);
    }

    /**
     * Hacia dónde mueve esta línea: la suya en un conteo, si no la del
     * ajuste. Null: en un conteo, sin cambio.
     */
    public function tipoMovimiento(TipoAjuste $delAjuste): ?TipoAjuste
    {
        if ($delAjuste === TipoAjuste::Conteo) {
            return $this->tipo;
        }

        return $delAjuste;
    }

    /**
     * Cuánto cambia la existencia, con signo: +50 entra, −5 sale.
     */
    public function cambio(TipoAjuste $delAjuste): float
    {
        return match ($this->tipoMovimiento($delAjuste)) {
            TipoAjuste::Entrada => (float) $this->cantidad,
            TipoAjuste::Salida  => -(float) $this->cantidad,
            default             => 0.0,
        };
    }

    /**
     * Costo unitario neto (sin ISV) con que se valora la línea. Al aplicar
     * el ajuste queda guardado; en un borrador sin costo, el promedio de hoy.
     */
    public function costoValorado(): float
    {
        if ((float) $this->costo_unitario > 0 || $this->ajuste->estado === EstadoDocumento::Confirmado) {
            return (float) $this->costo_unitario;
        }

        return (float) $this->producto->costo_promedio;
    }

    /**
     * Lo que vale el cambio con el ISV que se pagó al comprar: lo que se
     * pierde en una merma o lo que entra en un sobrante. Sin cambio, 0.
     */
    public function valorConIsv(): float
    {
        $valor = round((float) $this->cantidad * $this->costoValorado(), 2);

        return round($valor + round($valor * $this->producto->tipo_isv->tasa(), 2), 2);
    }
}
