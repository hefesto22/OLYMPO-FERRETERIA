<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoMovimientoCaja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ingreso o retiro de efectivo durante un turno (no es una venta).
 *
 * @property int $id
 * @property int $turno_caja_id
 * @property TipoMovimientoCaja $tipo
 * @property string $monto
 * @property string $motivo
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property-read TurnoCaja $turno
 * @property-read User|null $usuario
 */
class MovimientoCaja extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'movimientos_caja';

    /** @var list<string> */
    protected $fillable = [
        'turno_caja_id',
        'tipo',
        'monto',
        'motivo',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo'       => TipoMovimientoCaja::class,
            'monto'      => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TurnoCaja, $this>
     */
    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
