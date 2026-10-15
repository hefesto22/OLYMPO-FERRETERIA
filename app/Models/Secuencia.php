<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Contador con bloqueo de fila (lo usa NumeracionService para recibos).
 *
 * @property int $id
 * @property string $clave
 * @property int $siguiente
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Secuencia extends Model
{
    protected $table = 'secuencias';

    /** @var list<string> */
    protected $fillable = [
        'clave',
        'siguiente',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'siguiente' => 'integer',
        ];
    }
}
