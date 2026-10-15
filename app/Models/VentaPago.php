<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetodoPago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $venta_id
 * @property MetodoPago $metodo
 * @property string $monto
 * @property string|null $referencia
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Venta $venta
 */
class VentaPago extends Model
{
    protected $table = 'venta_pagos';

    /** @var list<string> */
    protected $fillable = [
        'venta_id',
        'metodo',
        'monto',
        'referencia',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metodo' => MetodoPago::class,
            'monto'  => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Venta, $this>
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}
