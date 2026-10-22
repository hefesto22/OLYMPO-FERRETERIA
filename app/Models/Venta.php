<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoVenta;
use App\Enums\ListaPrecio;
use App\Enums\TipoDescuento;
use App\Enums\TipoDocumentoVenta;
use App\Support\NumeroALetras;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Venta de mostrador. La crea y la anula VentaService; ninguna pantalla
 * escribe importes directamente.
 *
 * @property int $id
 * @property string $numero
 * @property TipoDocumentoVenta $tipo_documento
 * @property int $caja_id
 * @property int $turno_caja_id
 * @property int $user_id
 * @property int|null $cliente_id
 * @property string $cliente_nombre
 * @property string|null $cliente_rtn
 * @property ListaPrecio $lista_precio
 * @property string $condicion
 * @property EstadoVenta $estado
 * @property int|null $rango_cai_id
 * @property string|null $cai
 * @property string|null $cai_rango_desde
 * @property string|null $cai_rango_hasta
 * @property Carbon|null $cai_fecha_limite
 * @property array<string, string|null>|null $datos_emisor
 * @property string $descuento
 * @property TipoDescuento|null $descuento_global_tipo
 * @property string $descuento_global_valor
 * @property string $descuento_global
 * @property string $importe_exento
 * @property string $importe_gravado_15
 * @property string $importe_gravado_18
 * @property string $isv_15
 * @property string $isv_18
 * @property string $subtotal
 * @property string $total
 * @property string $efectivo_recibido
 * @property string $cambio
 * @property string|null $notas
 * @property Carbon|null $anulada_at
 * @property int|null $anulada_por
 * @property string|null $motivo_anulacion
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * @property-read Caja $caja
 * @property-read TurnoCaja $turno
 * @property-read User $cajero
 * @property-read Cliente|null $cliente
 * @property-read Collection<int, VentaDetalle> $detalles
 * @property-read Collection<int, VentaPago> $pagos
 * @property-read User|null $anuladaPor
 */
class Venta extends Model
{
    protected $table = 'ventas';

    /** @var list<string> */
    protected $fillable = [
        'notas',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'estado'    => 'completada',
        'condicion' => 'contado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_documento'         => TipoDocumentoVenta::class,
            'lista_precio'           => ListaPrecio::class,
            'estado'                 => EstadoVenta::class,
            'cai_fecha_limite'       => 'date',
            'datos_emisor'           => 'array',
            'descuento'              => 'decimal:2',
            'descuento_global_tipo'  => TipoDescuento::class,
            'descuento_global_valor' => 'decimal:2',
            'descuento_global'       => 'decimal:2',
            'importe_exento'         => 'decimal:2',
            'importe_gravado_15'     => 'decimal:2',
            'importe_gravado_18'     => 'decimal:2',
            'isv_15'                 => 'decimal:2',
            'isv_18'                 => 'decimal:2',
            'subtotal'               => 'decimal:2',
            'total'                  => 'decimal:2',
            'efectivo_recibido'      => 'decimal:2',
            'cambio'                 => 'decimal:2',
            'anulada_at'             => 'datetime',
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
     * @return BelongsTo<TurnoCaja, $this>
     */
    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id')->withTrashed();
    }

    /**
     * @return HasMany<VentaDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    /**
     * @return HasMany<VentaPago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class, 'venta_id');
    }

    /**
     * Salidas de kardex que generó la venta (y su reversa si se anuló).
     *
     * @return MorphMany<MovimientoInventario, $this>
     */
    public function movimientos(): MorphMany
    {
        return $this->morphMany(MovimientoInventario::class, 'documento');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    public function esFactura(): bool
    {
        return $this->tipo_documento === TipoDocumentoVenta::Factura;
    }

    public function estaAnulada(): bool
    {
        return $this->estado === EstadoVenta::Anulada;
    }

    /**
     * "Descuento a toda la venta 10 %" o, si fue en lempiras, sin el
     * porcentaje (el monto va al lado).
     */
    public function etiquetaDescuentoGlobal(): string
    {
        return $this->descuento_global_tipo === TipoDescuento::Porcentaje
            ? 'Descuento a toda la venta '.cantidadFormato((float) $this->descuento_global_valor, 2).' %'
            : 'Descuento a toda la venta';
    }

    public function totalEnLetras(): string
    {
        return NumeroALetras::convertir((float) $this->total);
    }
}
