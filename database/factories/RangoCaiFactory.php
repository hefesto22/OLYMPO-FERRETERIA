<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Caja;
use App\Models\RangoCai;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Rango vigente de 1 a 100 para la caja indicada.
 *
 * @extends Factory<RangoCai>
 */
class RangoCaiFactory extends Factory
{
    protected $model = RangoCai::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caja_id' => Caja::factory(),
            'cai'     => strtoupper(implode('-', [
                fake()->regexify('[A-F0-9]{6}'),
                fake()->regexify('[A-F0-9]{6}'),
                fake()->regexify('[A-F0-9]{6}'),
                fake()->regexify('[A-F0-9]{6}'),
                fake()->regexify('[A-F0-9]{6}'),
                fake()->regexify('[A-F0-9]{2}'),
            ])),
            'establecimiento'      => '000',
            'punto_emision'        => '001',
            'tipo_documento'       => '01',
            'rango_inicial'        => 1,
            'rango_final'          => 100,
            'siguiente'            => 1,
            'fecha_limite_emision' => now()->addMonths(6)->toDateString(),
            'activo'               => true,
        ];
    }

    public function vencido(): static
    {
        return $this->state(fn (array $attributes): array => [
            'fecha_limite_emision' => now()->subDay()->toDateString(),
        ]);
    }

    public function agotado(): static
    {
        return $this->state(fn (array $attributes): array => [
            'siguiente' => 101,
        ]);
    }
}
