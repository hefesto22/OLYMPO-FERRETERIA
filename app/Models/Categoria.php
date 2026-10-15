<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasAuditFields;
use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Categoría de productos, con un nivel opcional de subcategoría
 * (Electricidad → Cables).
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Categoria|null $parent
 */
class Categoria extends Model
{
    use HasAuditFields;

    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'categorias';

    /** @var list<string> */
    protected $fillable = [
        'parent_id',
        'nombre',
        'descripcion',
        'activo',
        'created_by',
        'updated_by',
        'deleted_by',
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
     * @return BelongsTo<Categoria, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Categoria, $this>
     */
    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Producto, $this>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }

    /**
     * Nombre con su categoría padre: "Electricidad › Cables".
     */
    public function nombreCompleto(): string
    {
        return $this->parent !== null
            ? $this->parent->nombre.' › '.$this->nombre
            : $this->nombre;
    }

    /**
     * @param Builder<Categoria> $query
     *
     * @return Builder<Categoria>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
