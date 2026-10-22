<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ListaPrecio;
use App\Traits\HasAuditFields;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Cliente de la ferretería. La venta sin cliente es "Consumidor final".
 * El nombre y la dirección se guardan en mayúsculas; la factura toma de
 * aquí el nombre y el RTN.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $rtn
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $direccion
 * @property ListaPrecio $tipo_precio
 * @property bool $permite_credito
 * @property string $limite_credito
 * @property int $dias_credito
 * @property string|null $notas
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Cliente extends Model
{
    use HasAuditFields;

    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'clientes';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'rtn',
        'telefono',
        'email',
        'direccion',
        'tipo_precio',
        'permite_credito',
        'limite_credito',
        'dias_credito',
        'notas',
        'activo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'tipo_precio'     => 'publico',
        'permite_credito' => false,
        'limite_credito'  => 0,
        'dias_credito'    => 0,
        'activo'          => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_precio'     => ListaPrecio::class,
            'permite_credito' => 'boolean',
            'limite_credito'  => 'decimal:2',
            'dias_credito'    => 'integer',
            'activo'          => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Nombre y dirección en mayúsculas, como van en la factura. Así quedan
        // venga de donde venga el cliente (punto de venta, pantalla de clientes, importación).
        static::saving(function (Cliente $cliente): void {
            $cliente->nombre = mb_strtoupper(trim($cliente->nombre));

            $direccion = trim((string) $cliente->direccion);
            $cliente->direccion = $direccion !== '' ? mb_strtoupper($direccion) : null;
        });
    }

    /**
     * @return HasMany<Venta, $this>
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }

    /**
     * "Constructora Mayap · RTN 08011999123456".
     */
    public function etiqueta(): string
    {
        return $this->rtn !== null && $this->rtn !== ''
            ? $this->nombre.' · RTN '.$this->rtn
            : $this->nombre;
    }
}
