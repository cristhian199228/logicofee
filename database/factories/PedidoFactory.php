<?php

namespace Database\Factories;

use App\Enums\EstadoPago;
use App\Enums\EstadoPedido;
use App\Enums\MetodoPago;
use App\Enums\TipoEntrega;
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
            'metodo_pago' => MetodoPago::Efectivo,
            'estado_pago' => EstadoPago::Pendiente,
            'referencia_pago' => null,
            'pago_detalle' => null,
            'pagado_at' => null,
            'tipo_entrega' => TipoEntrega::Delivery,
            'entrega_recibido_por' => null,
        ];
    }

    public function enEstado(EstadoPedido $estado): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => $estado,
            'entregado_at' => $estado === EstadoPedido::Entregado ? now() : null,
        ]);
    }

    /** Pedido cobrado con el medio indicado. */
    public function pagadoCon(MetodoPago $metodo): static
    {
        return $this->state(fn (array $attributes) => [
            'metodo_pago' => $metodo,
            'estado_pago' => EstadoPago::Pagado,
            'referencia_pago' => 'AUT-'.fake()->bothify('????####'),
            'pagado_at' => now(),
        ]);
    }

    /** Pedido con el cobro todavía abierto. */
    public function porCobrar(MetodoPago $metodo = MetodoPago::Efectivo): static
    {
        return $this->state(fn (array $attributes) => [
            'metodo_pago' => $metodo,
            'estado_pago' => EstadoPago::Pendiente,
            'pagado_at' => null,
        ]);
    }

    /** Pedido que el cliente recoge en el local: sin costo de envío. */
    public function recojoEnTienda(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_entrega' => TipoEntrega::RecojoEnTienda,
            'envio' => 0,
            'total' => $attributes['subtotal'],
        ]);
    }
}
