<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Datos del emisor y reglas de caja. Singleton: una sola fila, cacheada
 * (se lee en cada venta y en cada ticket) e invalidada al guardar.
 *
 * @property int $id
 * @property string $razon_social
 * @property string $nombre_comercial
 * @property string|null $rtn
 * @property string|null $direccion
 * @property string|null $telefono
 * @property string|null $correo
 * @property string $establecimiento
 * @property string|null $mensaje_ticket
 * @property string $descuento_max_cajero
 * @property string $descuento_max_supervisor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConfiguracionEmpresa extends Model
{
    private const CACHE_KEY = 'configuracion_empresa:actual';

    protected $table = 'configuracion_empresa';

    /** @var list<string> */
    protected $fillable = [
        'razon_social',
        'nombre_comercial',
        'rtn',
        'direccion',
        'telefono',
        'correo',
        'establecimiento',
        'mensaje_ticket',
        'descuento_max_cajero',
        'descuento_max_supervisor',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'descuento_max_cajero'     => 'decimal:2',
            'descuento_max_supervisor' => 'decimal:2',
        ];
    }

    public static function actual(): self
    {
        /** @var self $configuracion */
        $configuracion = Cache::rememberForever(
            self::CACHE_KEY,
            static fn (): self => self::query()->firstOrCreate([], [
                'razon_social'     => (string) config('app.name', 'Ferretería'),
                'nombre_comercial' => (string) config('app.name', 'Ferretería'),
                'establecimiento'  => '000',
                'mensaje_ticket'   => '¡Gracias por su compra!',
            ]),
        );

        return $configuracion;
    }

    protected static function booted(): void
    {
        static::saved(static fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(static fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Borra la copia en cache. `migrate:fresh` vacía la base pero no Redis:
     * sin esto quedaría una configuración fantasma de la base anterior.
     */
    public static function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Nombre visible en tickets: el comercial si existe, si no la razón social.
     */
    public function nombreVisible(): string
    {
        return $this->nombre_comercial !== '' ? $this->nombre_comercial : $this->razon_social;
    }

    /**
     * Copia que se guarda en cada factura al emitirla.
     *
     * @return array{razon_social: string, nombre_comercial: string, rtn: string|null, direccion: string|null, telefono: string|null, correo: string|null}
     */
    public function datosEmisor(): array
    {
        return [
            'razon_social'     => $this->razon_social,
            'nombre_comercial' => $this->nombre_comercial,
            'rtn'              => $this->rtn,
            'direccion'        => $this->direccion,
            'telefono'         => $this->telefono,
            'correo'           => $this->correo,
        ];
    }
}
