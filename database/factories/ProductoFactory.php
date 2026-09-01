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

    public function agotado(): static
    {
        return $this->has(Lote::factory()->agotado(), 'lotes');
    }
}
