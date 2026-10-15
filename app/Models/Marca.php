<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasAuditFields;
use Database\Factories\MarcaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Marca extends Model
{
    use HasAuditFields;

    /** @use HasFactory<MarcaFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'marcas';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
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
     * @return HasMany<Producto, $this>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'marca_id');
    }
}
