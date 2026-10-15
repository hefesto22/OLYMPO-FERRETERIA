<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UnidadMedidaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Unidad de medida: Unidad, Metro, Libra, Galón, Caja, Rollo, Quintal…
 *
 * @property int $id
 * @property string $nombre
 * @property string $abreviatura
 * @property bool $permite_decimales
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UnidadMedida extends Model
{
    /** @use HasFactory<UnidadMedidaFactory> */
    use HasFactory;

    protected $table = 'unidades_medida';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'abreviatura',
        'permite_decimales',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permite_decimales' => 'boolean',
            'activo'            => 'boolean',
        ];
    }

    /**
     * @return HasMany<Producto, $this>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'unidad_medida_id');
    }

    /**
     * @param Builder<UnidadMedida> $query
     *
     * @return Builder<UnidadMedida>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
