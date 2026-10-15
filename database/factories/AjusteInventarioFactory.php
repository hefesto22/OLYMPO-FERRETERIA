<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MotivoAjuste;
use App\Enums\TipoAjuste;
use App\Models\AjusteInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea el ajuste en borrador, sin líneas.
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
            'motivo' => MotivoAjuste::ConteoFisico,
        ];
    }

    public function salida(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo'   => TipoAjuste::Salida,
            'motivo' => MotivoAjuste::DanoMerma,
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
