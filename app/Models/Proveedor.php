<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasAuditFields;
use Database\Factories\ProveedorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $rtn
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $contacto
 * @property string|null $direccion
 * @property int $dias_credito
 * @property string|null $notas
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Proveedor extends Model
{
    use HasAuditFields;

    /** @use HasFactory<ProveedorFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'proveedores';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'rtn',
        'telefono',
        'email',
        'contacto',
        'direccion',
        'dias_credito',
        'notas',
        'activo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'dias_credito' => 0,
        'activo'       => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dias_credito' => 'integer',
            'activo'       => 'boolean',
        ];
    }

    /**
     * Facturas y recibos registrados en Compras.
     *
     * @return HasMany<Compra, $this>
     */
    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }

    /**
     * Mercadería que entró al inventario de este proveedor.
     *
     * @return HasMany<EntradaInventario, $this>
     */
    public function entradas(): HasMany
    {
        return $this->hasMany(EntradaInventario::class, 'proveedor_id');
    }

    /**
     * Con compras o entradas registradas no se borra: el historial lo necesita.
     */
    public function tieneMovimientos(): bool
    {
        return $this->compras()->withTrashed()->exists() || $this->entradas()->exists();
    }
}
