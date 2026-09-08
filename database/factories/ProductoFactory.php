<?php

namespace Database\Factories;

use App\Enums\CategoriaProducto;
use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = 'Café '.fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($nombre),
            'nombre' => Str::title($nombre),
            'presentacion' => fake()->randomElement(['250 g', '500 g', '1 kg']),
            'categoria' => fake()->randomElement(CategoriaProducto::cases()),
            'descripcion' => fake()->sentence(14),
            'imagen' => null,
            'precio' => fake()->randomFloat(2, 8, 30),
            'stock' => 0,
            'stock_minimo' => 15,
            'acento' => fake()->randomElement(['#4a7c3f', '#b5453a', '#17331c', '#7fa95c', '#d9a441']),
            'destacado' => false,
            'promocion_titulo' => null,
            'descuento' => 0,
            'promocion_inicia_at' => null,
            'promocion_termina_at' => null,
        ];
    }

    /**
     * El stock sale de los lotes, así que se sincroniza al terminar de crearlos.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (Producto $producto) => $producto->sincronizarStock());
    }

    /** Crea el producto con un único lote de la cantidad indicada. */
    public function conStock(int $stock): static
    {
        return $stock > 0
            ? $this->has(Lote::factory()->conCantidad($stock), 'lotes')
            : $this;
    }

    /** Producto destacado en el catálogo con una promoción vigente (HU03). */
    public function enPromocion(int $descuento = 20): static
    {
        return $this->state(fn (array $attributes) => [
            'destacado' => true,
            'promocion_titulo' => 'Promoción de temporada',
            'descuento' => $descuento,
            'promocion_inicia_at' => now()->subDay(),
            'promocion_termina_at' => now()->addWeek(),
        ]);
    }

    /** Destacado con una vigencia que ya terminó. */
    public function promocionVencida(): static
    {
        return $this->state(fn (array $attributes) => [
            'destacado' => true,
            'descuento' => 20,
            'promocion_inicia_at' => now()->subMonth(),
            'promocion_termina_at' => now()->subDay(),
        ]);
    }

    public function agotado(): static
    {
        return $this->has(Lote::factory()->agotado(), 'lotes');
    }
}
