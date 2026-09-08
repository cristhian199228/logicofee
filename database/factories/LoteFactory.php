<?php

namespace Database\Factories;

use App\Enums\ResultadoCalidad;
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
            'calidad' => ResultadoCalidad::Pendiente,
            'calidad_nota' => null,
            'evaluado_at' => null,
            'evaluado_por' => null,
            'cantidad_baja' => 0,
            'baja_nota' => null,
            'dado_de_baja_at' => null,
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

    public function aprobado(): static
    {
        return $this->state(fn (array $attributes) => [
            'calidad' => ResultadoCalidad::Aprobado,
            'evaluado_at' => now(),
        ]);
    }

    /** Lote rechazado en control de calidad: bloqueado para la venta (HU07). */
    public function rechazado(): static
    {
        return $this->state(fn (array $attributes) => [
            'calidad' => ResultadoCalidad::Rechazado,
            'calidad_nota' => 'Humedad fuera de rango.',
            'evaluado_at' => now(),
        ]);
    }

    /** Lote que ya venció y sigue ocupando unidades en el almacén. */
    public function vencido(): static
    {
        return $this->state(fn (array $attributes) => [
            'tostado_at' => now()->subMonths(13),
            'vence_at' => now()->subWeek(),
        ]);
    }

    /** Lote con unidades ya retiradas del almacén por merma. */
    public function conMerma(int $cantidad): static
    {
        return $this->state(fn (array $attributes) => [
            'cantidad_disponible' => $attributes['cantidad_disponible'] - $cantidad,
            'cantidad_baja' => $cantidad,
            'baja_nota' => 'Lote vencido retirado del almacén.',
            'dado_de_baja_at' => now(),
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
