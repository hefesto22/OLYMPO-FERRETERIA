<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CajaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Caja registradora = punto de emisión ante el SAR.
 *
 * @property int $id
 * @property string $nombre
 * @property string $punto_emision
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RangoCai|null $rangoActivo
 * @property-read TurnoCaja|null $turnoAbierto
 */
class Caja extends Model
{
    /** @use HasFactory<CajaFactory> */
    use HasFactory;

    protected $table = 'cajas';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'punto_emision',
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
            'activo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RangoCai, $this>
     */
    public function rangosCai(): HasMany
    {
        return $this->hasMany(RangoCai::class, 'caja_id');
    }

    /**
     * @return HasOne<RangoCai, $this>
     */
    public function rangoActivo(): HasOne
    {
        return $this->hasOne(RangoCai::class, 'caja_id')->where('activo', true);
    }

    /**
     * @return HasMany<TurnoCaja, $this>
     */
    public function turnos(): HasMany
    {
        return $this->hasMany(TurnoCaja::class, 'caja_id');
    }

    /**
     * @return HasOne<TurnoCaja, $this>
     */
    public function turnoAbierto(): HasOne
    {
        return $this->hasOne(TurnoCaja::class, 'caja_id')->where('estado', 'abierto');
    }
}
