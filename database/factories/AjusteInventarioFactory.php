<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
use App\Models\AjusteInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea el ajuste en borrador, sin líneas. Por defecto "Otro" que suma:
 * es el único motivo que respeta el tipo que se le da.
 *
 * @extends Factory<AjusteInventario>
 */
class AjusteInventarioFactory extends Factory
{
    protected $model = AjusteInventario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha'  => now()->toDateString(),
            'tipo'   => TipoAjuste::Entrada,
            'motivo' => MotivoAjuste::Otro,
        ];
    }

    public function salida(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo'   => TipoAjuste::Salida,
            'motivo' => MotivoAjuste::DanoMerma,
        ]);
    }

    /**
     * Conteo físico: cada línea lleva lo contado.
     */
    public function conteo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo'   => TipoAjuste::Conteo,
            'motivo' => MotivoAjuste::ConteoFisico,
        ]);
    }

    public function inventarioInicial(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo'   => TipoAjuste::Entrada,
            'motivo' => MotivoAjuste::InventarioInicial,
        ]);
    }
}
