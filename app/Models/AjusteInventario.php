<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoDocumento;
use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
use App\Support\NumeroDocumento;
use App\Traits\HasAuditFields;
use Database\Factories\AjusteInventarioFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Ajuste de inventario (inventario inicial, conteo físico, merma…).
 *
 * El motivo decide hacia dónde mueve (ver MotivoAjuste::tipo()); solo
 * "Otro" guarda el tipo que se escogió. La fecha es la del día en que se
 * registra y quién lo registró lo guarda HasAuditFields: los dos solos.
 *
 * @property int $id
 * @property string|null $numero
 * @property Carbon $fecha
 * @property TipoAjuste $tipo
 * @property MotivoAjuste $motivo
 * @property EstadoDocumento $estado
 * @property string|null $notas
 * @property Carbon|null $aplicado_at
 * @property int|null $aplicado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AjusteInventarioDetalle> $detalles
 * @property-read User|null $aplicadoPor
 */
class AjusteInventario extends Model
{
    use HasAuditFields;

    /** @use HasFactory<AjusteInventarioFactory> */
    use HasFactory;

    protected $table = 'ajustes_inventario';

    /** @var list<string> */
    protected $fillable = [
        'fecha',
        'tipo',
        'motivo',
        'notas',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'estado' => 'borrador',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha'       => 'date',
            'tipo'        => TipoAjuste::class,
            'motivo'      => MotivoAjuste::class,
            'estado'      => EstadoDocumento::class,
            'aplicado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (AjusteInventario $ajuste): void {
            if ($ajuste->getAttribute('fecha') === null) {
                $ajuste->setAttribute('fecha', now()->toDateString());
            }
        });

        static::saving(static function (AjusteInventario $ajuste): void {
            $tipo = $ajuste->motivo->tipo();

            if ($tipo !== null) {
                $ajuste->setAttribute('tipo', $tipo);
            }
        });

        static::created(static function (AjusteInventario $ajuste): void {
            $prefijo = (string) config('ferreteria.documentos.prefijo_ajuste', 'AJ');

            $ajuste->forceFill(['numero' => NumeroDocumento::formatear($prefijo, $ajuste->id)])->saveQuietly();
        });
    }

    /**
     * @return HasMany<AjusteInventarioDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(AjusteInventarioDetalle::class, 'ajuste_inventario_id');
    }

    /**
     * @return MorphMany<MovimientoInventario, $this>
     */
    public function movimientos(): MorphMany
    {
        return $this->morphMany(MovimientoInventario::class, 'documento');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function aplicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicado_por');
    }

    public function esBorrador(): bool
    {
        return $this->estado === EstadoDocumento::Borrador;
    }
}
