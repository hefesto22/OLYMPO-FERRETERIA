<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoTurno;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Turno de caja. Lo abre y lo cierra TurnoCajaService; al cerrar se
 * guarda la foto de los totales.
 *
 * @property int $id
 * @property int $caja_id
 * @property int $user_id
 * @property EstadoTurno $estado
 * @property string $fondo_inicial
 * @property Carbon $abierto_at
 * @property Carbon|null $cerrado_at
 * @property int|null $cerrado_por
 * @property int $cantidad_ventas
 * @property string $total_ventas
 * @property string $total_efectivo
 * @property string $total_tarjeta
 * @property string $total_transferencia
 * @property string $total_ingresos
 * @property string $total_egresos
 * @property string $efectivo_esperado
 * @property string|null $efectivo_contado
 * @property string|null $diferencia
 * @property string|null $notas_cierre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Caja $caja
 * @property-read User $cajero
 * @property-read User|null $cerradoPor
 */
class TurnoCaja extends Model
{
    protected $table = 'turnos_caja';

    /** @var list<string> */
    protected $fillable = [
        'notas_cierre',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'estado' => 'abierto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado'              => EstadoTurno::class,
            'fondo_inicial'       => 'decimal:2',
            'abierto_at'          => 'datetime',
            'cerrado_at'          => 'datetime',
            'cantidad_ventas'     => 'integer',
            'total_ventas'        => 'decimal:2',
            'total_efectivo'      => 'decimal:2',
            'total_tarjeta'       => 'decimal:2',
            'total_transferencia' => 'decimal:2',
            'total_ingresos'      => 'decimal:2',
            'total_egresos'       => 'decimal:2',
            'efectivo_esperado'   => 'decimal:2',
            'efectivo_contado'    => 'decimal:2',
            'diferencia'          => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Caja, $this>
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    /**
     * @return HasMany<Venta, $this>
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'turno_caja_id');
    }

    /**
     * @return HasMany<MovimientoCaja, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class, 'turno_caja_id');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === EstadoTurno::Abierto;
    }
}
