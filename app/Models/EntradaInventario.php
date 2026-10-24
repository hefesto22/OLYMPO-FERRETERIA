<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoDocumento;
use App\Support\NumeroDocumento;
use App\Traits\HasAuditFields;
use Database\Factories\EntradaInventarioFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Entrada de mercadería al inventario: qué productos entran, cuántos y
 * a qué costo. Es el único lugar donde se carga stock comprado; la
 * factura o el recibo del proveedor se registra aparte, en Compras.
 *
 * Solo guarda quién la registró (created_by) y la fecha, los dos solos:
 * la fecha es la del día en que se registra.
 *
 * Estado, totales y datos de confirmación/anulación NO son fillable:
 * los maneja EntradaInventarioService.
 *
 * @property int $id
 * @property string|null $numero
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
 * @property-read Collection<int, EntradaInventarioDetalle> $detalles
 * @property-read User|null $confirmadaPor
 * @property-read User|null $anuladaPor
 */
class EntradaInventario extends Model
{
    use HasAuditFields;

    /** @use HasFactory<EntradaInventarioFactory> */
    use HasFactory;

    protected $table = 'entradas_inventario';

    /** @var list<string> */
    protected $fillable = [
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
        static::creating(static function (EntradaInventario $entrada): void {
            if ($entrada->getAttribute('fecha') === null) {
                $entrada->setAttribute('fecha', now()->toDateString());
            }
        });

        static::created(static function (EntradaInventario $entrada): void {
            $prefijo = (string) config('ferreteria.documentos.prefijo_entrada', 'E');

            $entrada->forceFill(['numero' => NumeroDocumento::formatear($prefijo, $entrada->id)])->saveQuietly();
        });
    }

    /**
     * @return HasMany<EntradaInventarioDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(EntradaInventarioDetalle::class, 'entrada_inventario_id');
    }

    /**
     * Movimientos de kardex que generó esta entrada.
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
