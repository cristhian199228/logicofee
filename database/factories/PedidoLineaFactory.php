<?php

namespace Database\Factories;

use App\Enums\CategoriaProducto;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PedidoLinea>
 */
class PedidoLineaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pedido_id' => Pedido::factory(),
            'producto_id' => Producto::factory(),
            'nombre' => fake()->words(2, true),
            'presentacion' => '250 g',
            'categoria' => fake()->randomElement(CategoriaProducto::cases()),
            'precio' => fake()->randomFloat(2, 8, 30),
            'cantidad' => fake()->numberBetween(1, 12),
        ];
    }

    /** Copia los datos del producto, tal como lo hace el registro de pedidos. */
    public function deProducto(Producto $producto, int $cantidad): static
    {
        return $this->state(fn (array $attributes) => [
            'producto_id' => $producto->id,
            'nombre' => $producto->nombre,
            'presentacion' => $producto->presentacion,
            'categoria' => $producto->categoria,
            'precio' => $producto->precio,
            'cantidad' => $cantidad,
        ]);
    }
}
