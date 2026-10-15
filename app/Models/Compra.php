<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoDocumento;
use App\Support\NumeroDocumento;
use App\Traits\HasAuditFields;
use Database\Factories\CompraFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Compra a proveedor (entrada de mercadería).
 *
 * Estado, totales y datos de confirmación/anulación NO son fillable:
 * los maneja CompraService.
 *
 * @property int $id
 * @property string|null $numero
 * @property int $proveedor_id
 * @property string|null $numero_factura_proveedor
 * @property Carbon $fecha
 * @property EstadoDocumento $estado
 * @property string $subtotal
 * @property string $isv
 * @property string $total
 * @property string|null $notas
 * @property Carbon|null $confirmada_at
 * @property int|null $confirmada_por
 * @property Carbon|null $anulada_at
 * @property int|null $anulada_por
 * @property string|null $motivo_anulacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Proveedor $proveedor
 * @property-read Collection<int, CompraDetalle> $detalles
 * @property-read User|null $confirmadaPor
 * @property-read User|null $anuladaPor
 */
class Compra extends Model
{
    use HasAuditFields;

    /** @use HasFactory<CompraFactory> */
    use HasFactory;

    protected $table = 'compras';

    /** @var list<string> */
    protected $fillable = [
        'proveedor_id',
        'numero_factura_proveedor',
        'fecha',
        'notas',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'estado'   => 'borrador',
        'subtotal' => 0,
        'isv'      => 0,
        'total'    => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha'         => 'date',
            'estado'        => EstadoDocumento::class,
            'subtotal'      => 'decimal:2',
            'isv'           => 'decimal:2',
            'total'         => 'decimal:2',
            'confirmada_at' => 'datetime',
            'anulada_at'    => 'datetime',
        ];
    }

    protected static function booted(): void
    {
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
     * @return HasMany<CompraDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    /**
     * Movimientos de kardex que generó esta compra.
     *
     * @return MorphMany<MovimientoInventario, $this>
     */
    public function movimientos(): MorphMany
    {
        return $this->morphMany(MovimientoInventario::class, 'documento');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmada_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    public function esBorrador(): bool
    {
        return $this->estado === EstadoDocumento::Borrador;
    }
}
