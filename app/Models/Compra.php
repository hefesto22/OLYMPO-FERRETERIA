<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoriaGasto;
use App\Enums\TipoDocumentoCompra;
use App\Enums\TipoIsv;
use App\Support\NumeroDocumento;
use App\Traits\HasAuditFields;
use Database\Factories\CompraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Compra o gasto del negocio: la factura o el recibo que dio el
 * proveedor, solo con sus totales. No toca el inventario: los productos
 * se cargan en Entradas de inventario.
 *
 * El ISV de una factura con el RTN del proveedor es crédito fiscal: se
 * descuenta del ISV de las ventas y va al Libro de Compras del SAR. Un
 * recibo solo guarda lo pagado (en `exento`).
 *
 * El ISV total y el total se calculan aquí al guardar.
 *
 * @property int $id
 * @property string|null $numero
 * @property TipoDocumentoCompra $tipo_documento
 * @property Carbon $fecha
 * @property string|null $numero_factura
 * @property CategoriaGasto $categoria
 * @property int|null $proveedor_id
 * @property string|null $rtn_proveedor
 * @property string $gravado_15
 * @property string $isv_15
 * @property string $gravado_18
 * @property string $isv_18
 * @property string $exento
 * @property string $isv
 * @property string $total
 * @property string|null $notas
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Proveedor|null $proveedor
 */
class Compra extends Model
{
    use HasAuditFields;

    /** @use HasFactory<CompraFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'compras';

    /** @var list<string> */
    protected $fillable = [
        'tipo_documento',
        'fecha',
        'numero_factura',
        'categoria',
        'proveedor_id',
        'rtn_proveedor',
        'gravado_15',
        'isv_15',
        'gravado_18',
        'isv_18',
        'exento',
        'notas',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'tipo_documento' => 'factura',
        'gravado_15'     => 0,
        'isv_15'         => 0,
        'gravado_18'     => 0,
        'isv_18'         => 0,
        'exento'         => 0,
        'isv'            => 0,
        'total'          => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumentoCompra::class,
            'categoria'      => CategoriaGasto::class,
            'fecha'          => 'date',
            'gravado_15'     => 'decimal:2',
            'isv_15'         => 'decimal:2',
            'gravado_18'     => 'decimal:2',
            'isv_18'         => 'decimal:2',
            'exento'         => 'decimal:2',
            'isv'            => 'decimal:2',
            'total'          => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (Compra $compra): void {
            $compra->calcularTotales();
        });

        static::created(static function (Compra $compra): void {
            $prefijo = (string) config('ferreteria.documentos.prefijo_compra', 'C');

            $compra->forceFill(['numero' => NumeroDocumento::formatear($prefijo, $compra->id)])->saveQuietly();
        });
    }

    /**
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    /**
     * ISV de la factura y total. Un recibo no separa ISV: todo lo pagado
     * va en `exento` y los gravados quedan en cero.
     */
    public function calcularTotales(): void
    {
        if ($this->tipo_documento === TipoDocumentoCompra::Recibo) {
            $this->forceFill(['gravado_15' => 0, 'isv_15' => 0, 'gravado_18' => 0, 'isv_18' => 0]);
        }

        $isv = round((float) $this->isv_15 + (float) $this->isv_18, 2);

        $this->forceFill([
            'isv'   => $isv,
            'total' => round((float) $this->gravado_15 + (float) $this->gravado_18 + $isv + (float) $this->exento, 2),
        ]);
    }

    /**
     * ISV que se puede descontar del ISV de las ventas: el de una factura
     * con el RTN del proveedor.
     */
    public function isvAcreditable(): float
    {
        return $this->tipo_documento === TipoDocumentoCompra::Factura && filled($this->rtn_proveedor)
            ? (float) $this->isv
            : 0.0;
    }

    /**
     * ISV que corresponde a un gravado: 15 % o 18 % de lo gravado.
     */
    public static function isvDe(float $gravado, TipoIsv $tipo): float
    {
        return round(max($gravado, 0.0) * $tipo->tasa(), 2);
    }

    /**
     * Si el proveedor no tenía RTN y en la compra se escribió uno, se le
     * guarda para la próxima. Nunca le cambia un RTN que ya tenía, ni le
     * pone uno que ya es de otro proveedor.
     */
    public function guardarRtnEnProveedor(): void
    {
        $proveedor = $this->proveedor;
        $rtn = $this->rtn_proveedor;

        if ($proveedor === null || blank($rtn) || filled($proveedor->rtn)) {
            return;
        }

        if (Proveedor::withTrashed()->where('rtn', $rtn)->exists()) {
            return;
        }

        $proveedor->forceFill(['rtn' => $rtn])->save();
    }
}
