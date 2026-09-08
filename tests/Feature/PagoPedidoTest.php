<?php

namespace Tests\Feature;

use App\Enums\EstadoPago;
use App\Enums\MetodoPago;
use App\Enums\Rol;
use App\Enums\TipoEntrega;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Support\PasarelaPagoSimulada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PagoPedidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pago_en_efectivo_queda_por_cobrar_hasta_la_entrega(): void
    {
        $this->registrar(['metodo_pago' => MetodoPago::Efectivo->value]);

        $pedido = Pedido::firstOrFail();

        $this->assertSame(MetodoPago::Efectivo, $pedido->metodo_pago);
        $this->assertSame(EstadoPago::Pendiente, $pedido->estado_pago);
        $this->assertNull($pedido->pagado_at);
        $this->assertNull($pedido->referencia_pago);
    }

    public function test_el_pago_con_tarjeta_se_aprueba_y_guarda_la_operacion(): void
    {
        $this->registrar([
            'metodo_pago' => MetodoPago::Tarjeta->value,
            'tarjeta_numero' => '4242 4242 4242 4242',
            'tarjeta_titular' => 'C. Vargas',
            'tarjeta_vencimiento' => '12/30',
            'tarjeta_cvv' => '123',
        ])->assertSessionHasNoErrors();

        $pedido = Pedido::firstOrFail();

        $this->assertSame(EstadoPago::Pagado, $pedido->estado_pago);
        $this->assertStringStartsWith('AUT-', $pedido->referencia_pago);
        // De la tarjeta solo se guardan los últimos cuatro dígitos.
        $this->assertSame('Tarjeta ****4242', $pedido->pago_detalle);
        $this->assertNotNull($pedido->pagado_at);
        $this->assertDatabaseMissing('pedidos', ['pago_detalle' => '4242 4242 4242 4242']);
    }

    public function test_el_pago_con_yape_se_aprueba_con_el_celular_enmascarado(): void
    {
        $this->registrar([
            'metodo_pago' => MetodoPago::Yape->value,
            'yape_celular' => '987654321',
        ])->assertSessionHasNoErrors();

        $pedido = Pedido::firstOrFail();

        $this->assertSame(EstadoPago::Pagado, $pedido->estado_pago);
        $this->assertStringStartsWith('YPE-', $pedido->referencia_pago);
        $this->assertSame('Yape *****4321', $pedido->pago_detalle);
    }

    public function test_la_tarjeta_de_prueba_rechazada_no_registra_el_pedido(): void
    {
        $producto = Producto::factory()->conStock(5)->create();

        $this->registrar([
            'metodo_pago' => MetodoPago::Tarjeta->value,
            'tarjeta_numero' => '4111 1111 1111 '.PasarelaPagoSimulada::TERMINACION_RECHAZADA,
            'tarjeta_titular' => 'C. Vargas',
            'tarjeta_vencimiento' => '12/30',
            'tarjeta_cvv' => '123',
        ], $producto)->assertSessionHasErrors('pago');

        $this->assertDatabaseCount('pedidos', 0);
        // El stock vuelve a su sitio: la transacción se revierte completa.
        $this->assertSame(5, $producto->fresh()->stock);
    }

    public function test_el_yape_rechazado_no_registra_el_pedido(): void
    {
        $this->registrar([
            'metodo_pago' => MetodoPago::Yape->value,
            'yape_celular' => '98765'.PasarelaPagoSimulada::TERMINACION_RECHAZADA,
        ])->assertSessionHasErrors('pago');

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_la_tarjeta_exige_sus_datos_y_rechaza_la_vencida(): void
    {
        $this->registrar(['metodo_pago' => MetodoPago::Tarjeta->value])
            ->assertSessionHasErrors(['tarjeta_numero', 'tarjeta_titular', 'tarjeta_vencimiento', 'tarjeta_cvv']);

        $this->registrar([
            'metodo_pago' => MetodoPago::Tarjeta->value,
            'tarjeta_numero' => '4242424242424242',
            'tarjeta_titular' => 'C. Vargas',
            'tarjeta_vencimiento' => '01/20',
            'tarjeta_cvv' => '123',
        ])->assertSessionHasErrors('tarjeta_vencimiento');

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_yape_exige_un_celular_valido(): void
    {
        $this->registrar(['metodo_pago' => MetodoPago::Yape->value])
            ->assertSessionHasErrors('yape_celular');

        $this->registrar(['metodo_pago' => MetodoPago::Yape->value, 'yape_celular' => '12345'])
            ->assertSessionHasErrors('yape_celular');

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_el_recojo_en_tienda_no_cobra_envio(): void
    {
        $producto = Producto::factory()->conStock(5)->create(['precio' => 20.00]);

        $this->registrar([
            'tipo_entrega' => TipoEntrega::RecojoEnTienda->value,
            'cliente_direccion' => null,
        ], $producto)->assertSessionHasNoErrors();

        $pedido = Pedido::firstOrFail();

        $this->assertSame(TipoEntrega::RecojoEnTienda, $pedido->tipo_entrega);
        $this->assertSame('0.00', $pedido->envio);
        $this->assertSame('20.00', $pedido->total);
    }

    public function test_el_delivery_exige_una_direccion_de_entrega(): void
    {
        $this->registrar(['tipo_entrega' => TipoEntrega::Delivery->value, 'cliente_direccion' => null])
            ->assertSessionHasErrors('cliente_direccion');

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_el_delivery_suma_el_costo_de_envio(): void
    {
        $producto = Producto::factory()->conStock(5)->create(['precio' => 20.00]);

        $this->registrar([], $producto)->assertSessionHasNoErrors();

        $pedido = Pedido::firstOrFail();

        $this->assertSame(number_format((float) config('logicoffee.envio'), 2, '.', ''), $pedido->envio);
        $this->assertSame(20.0 + (float) config('logicoffee.envio'), (float) $pedido->total);
    }

    public function test_ventas_registra_el_cobro_de_un_pedido_pendiente(): void
    {
        $pedido = Pedido::factory()->porCobrar()->create();

        $this->actingAs(User::factory()->conRol(Rol::MarketingVentas)->create())
            ->patch(route('pedidos.pago.update', $pedido))
            ->assertRedirect();

        $pedido->refresh();

        $this->assertSame(EstadoPago::Pagado, $pedido->estado_pago);
        $this->assertSame('COB-'.$pedido->codigo, $pedido->referencia_pago);
        $this->assertNotNull($pedido->pagado_at);
    }

    public function test_el_cliente_no_registra_cobros(): void
    {
        $pedido = Pedido::factory()->porCobrar()->create();

        $this->actingAs(User::factory()->conRol(Rol::Cliente)->create())
            ->patch(route('pedidos.pago.update', $pedido))
            ->assertForbidden();

        $this->assertSame(EstadoPago::Pendiente, $pedido->fresh()->estado_pago);
    }

    public function test_el_historial_muestra_el_medio_de_pago_y_lo_que_falta_cobrar(): void
    {
        Pedido::factory()->porCobrar()->create(['codigo' => 'PED-501', 'total' => 120.00]);
        Pedido::factory()->pagadoCon(MetodoPago::Yape)->create(['codigo' => 'PED-502']);

        $this->actingAs(User::factory()->conRol(Rol::Administrador)->create())
            ->get(route('pedidos.index'))
            ->assertOk()
            ->assertSee('Forma de pago')
            ->assertSee('Yape')
            // El importe y la etiqueta viven en elementos distintos.
            ->assertSeeInOrder(['$120.00', 'por cobrar'])
            ->assertViewHas('porCobrar', 120.00);
    }

    /**
     * Registra un pedido de un producto con stock, sobreescribiendo los datos
     * del formulario con lo que cada prueba necesita.
     *
     * @param  array<string, mixed>  $datos
     */
    private function registrar(array $datos = [], ?Producto $producto = null): TestResponse
    {
        $producto ??= Producto::factory()->conStock(5)->create();
        $cliente = User::factory()->conRol(Rol::Cliente)->create();

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);

        return $this->actingAs($cliente)->post(route('pedidos.store'), [
            'cliente_nombre' => 'Cafetería Andina',
            'cliente_telefono' => '945664313',
            'cliente_tipo' => 'Cafetería',
            'cliente_direccion' => 'Av. Ejército 401, Yanahuara',
            'tipo_entrega' => TipoEntrega::Delivery->value,
            'metodo_pago' => MetodoPago::Efectivo->value,
            ...$datos,
        ]);
    }
}
