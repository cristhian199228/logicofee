<?php

namespace Database\Factories;

use App\Enums\EstadoPedido;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pedido>
 */
class PedidoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 20, 400);
        $envio = (float) config('logicoffee.envio');

        return [
            'codigo' => 'PED-'.fake()->unique()->numberBetween(100, 999),
            'user_id' => null,
            'cliente_nombre' => fake()->company(),
            'cliente_telefono' => fake()->numerify('9########'),
            'cliente_correo' => fake()->safeEmail(),
            'cliente_tipo' => fake()->randomElement(config('logicoffee.tipos_cliente')),
            'cliente_direccion' => fake()->streetAddress(),
            'observaciones' => null,
            'subtotal' => $subtotal,
            'envio' => $envio,
            'total' => $subtotal + $envio,
            'estado' => EstadoPedido::Pendiente,
            'entregado_at' => null,
        ];
    }

    public function enEstado(EstadoPedido $estado): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => $estado,
            'entregado_at' => $estado === EstadoPedido::Entregado ? now() : null,
        ]);
    }
}
