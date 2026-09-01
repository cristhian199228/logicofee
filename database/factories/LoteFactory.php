<?php

namespace Database\Factories;

use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lote>
 */
class LoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(20, 200);
        $tostado = fake()->dateTimeBetween('-4 months', '-1 week');

        return [
            'producto_id' => Producto::factory(),
            'codigo' => 'L-'.fake()->unique()->numberBetween(1000, 9999),
            'cantidad_inicial' => $cantidad,
            'cantidad_disponible' => $cantidad,
            'tostado_at' => $tostado,
            'vence_at' => (clone $tostado)->modify('+12 months'),
        ];
    }

    public function conCantidad(int $cantidad): static
    {
        return $this->state(fn (array $attributes) => [
            'cantidad_inicial' => $cantidad,
            'cantidad_disponible' => $cantidad,
        ]);
    }

    public function agotado(): static
    {
        return $this->state(fn (array $attributes) => [
            'cantidad_disponible' => 0,
        ]);
    }

    public function porVencer(): static
    {
        return $this->state(fn (array $attributes) => [
            'tostado_at' => now()->subMonths(11),
            'vence_at' => now()->addDays(15),
        ]);
    }
}
