<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasAuditFields;
use Carbon\CarbonInterface;
use Database\Factories\RangoCaiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Rango de facturas autorizado por el SAR para una caja.
 *
 * `siguiente` solo lo avanza NumeracionService, con bloqueo de fila.
 *
 * @property int $id
 * @property int $caja_id
 * @property string $cai
 * @property string $establecimiento
 * @property string $punto_emision
 * @property string $tipo_documento
 * @property int $rango_inicial
 * @property int $rango_final
 * @property int $siguiente
 * @property Carbon $fecha_limite_emision
 * @property bool $activo
 * @property string|null $notas
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Caja $caja
 */
class RangoCai extends Model
{
    use HasAuditFields;

    /** @use HasFactory<RangoCaiFactory> */
    use HasFactory;

    /** Días antes del vencimiento en que se empieza a avisar. */
    public const DIAS_AVISO = 30;

    /** Facturas restantes a partir de las cuales se empieza a avisar. */
    public const RESTANTES_AVISO = 100;

    protected $table = 'rangos_cai';

    /** @var list<string> */
    protected $fillable = [
        'caja_id',
        'cai',
        'establecimiento',
        'punto_emision',
        'tipo_documento',
        'rango_inicial',
        'rango_final',
        'siguiente',
        'fecha_limite_emision',
        'activo',
        'notas',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'tipo_documento' => '01',
        'activo'         => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rango_inicial'        => 'integer',
            'rango_final'          => 'integer',
            'siguiente'            => 'integer',
            'fecha_limite_emision' => 'date',
            'activo'               => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Un rango nuevo empieza en su número inicial.
        static::creating(static function (RangoCai $rango): void {
            if ($rango->getAttribute('siguiente') === null) {
                $rango->setAttribute('siguiente', $rango->rango_inicial);
            }
        });

        // Solo un rango activo por caja: al activar uno se apaga el anterior.
        static::saving(static function (RangoCai $rango): void {
            if (! $rango->activo) {
                return;
            }

            self::query()
                ->where('caja_id', $rango->caja_id)
                ->where('activo', true)
                ->when($rango->exists, fn ($query) => $query->whereKeyNot($rango->getKey()))
                ->update(['activo' => false]);
        });
    }

    /**
     * @return BelongsTo<Caja, $this>
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    /**
     * @return HasMany<Venta, $this>
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'rango_cai_id');
    }

    /**
     * 000-001-01-00000123
     */
    public function formatearNumero(int $correlativo): string
    {
        return sprintf('%s-%s-%s-%08d', $this->establecimiento, $this->punto_emision, $this->tipo_documento, $correlativo);
    }

    public function restantes(): int
    {
        return max(0, $this->rango_final - $this->siguiente + 1);
    }

    public function estaAgotado(): bool
    {
        return $this->siguiente > $this->rango_final;
    }

    /**
     * La fecha límite es el último día válido para emitir.
     */
    public function estaVencido(?CarbonInterface $hoy = null): bool
    {
        $hoy ??= Carbon::today();

        return $hoy->copy()->startOfDay()->greaterThan($this->fecha_limite_emision->copy()->startOfDay());
    }

    public function diasParaVencer(?CarbonInterface $hoy = null): int
    {
        $hoy ??= Carbon::today();

        return (int) $hoy->copy()->startOfDay()->diffInDays($this->fecha_limite_emision->copy()->startOfDay(), false);
    }

    public function etiquetaEstado(): string
    {
        return match (true) {
            ! $this->activo      => 'Inactivo',
            $this->estaAgotado() => 'Agotado',
            $this->estaVencido() => 'Vencido',
            $this->diasParaVencer() <= self::DIAS_AVISO,
            $this->restantes() <= self::RESTANTES_AVISO => 'Por terminarse',
            default                                     => 'Vigente',
        };
    }

    public function colorEstado(): string
    {
        return match ($this->etiquetaEstado()) {
            'Vigente'        => 'success',
            'Por terminarse' => 'warning',
            'Inactivo'       => 'gray',
            default          => 'danger',
        };
    }
}
