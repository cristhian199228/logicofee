<?php

namespace Tests\Feature;

use App\Enums\MetodoPago;
use App\Enums\Rol;
use App\Enums\TipoEntrega;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromocionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_catalogo_muestra_la_seccion_de_promociones_vigentes(): void
    {
        Producto::factory()->conStock(20)->enPromocion(15)->create(['nombre' => 'Origen Colombia']);

        $this->actingAs($this->cliente())
            ->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('Promociones vigentes')
            ->assertSee('Origen Colombia')
            ->assertSee('-15%');
    }

    public function test_un_destacado_fuera_de_vigencia_no_aparece_en_promociones(): void
    {
        Producto::factory()->conStock(20)->promocionVencida()->create(['nombre' => 'Geisha Premium']);

        $this->actingAs($this->cliente())
            ->get(route('catalogo.index'))
            ->assertOk()
            ->assertDontSee('Promociones vigentes');
    }

    public function test_el_pedido_cobra_el_precio_con_descuento(): void
    {
        $producto = Producto::factory()->conStock(20)->enPromocion(25)->create(['precio' => 20.00]);
        $cliente = $this->cliente();

        $this->assertEquals(15.00, $producto->precioVigente());

        $this->actingAs($cliente)->post(route('carrito.store'), ['producto' => $producto->slug]);
        $this->actingAs($cliente)->patch(route('carrito.update', $producto), ['delta' => 1]);

        $this->actingAs($cliente)
            ->post(route('pedidos.store'), $this->datosCliente())
            ->assertSessionHasNoErrors();

        $pedido = Pedido::sole();

        $this->assertEquals(30.00, $pedido->subtotal);
        $this->assertEquals(15.00, $pedido->lineas->sole()->precio);
    }

    public function test_el_administrador_destaca_un_producto_con_vigencia(): void
    {
        $producto = Producto::factory()->conStock(10)->create();

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), [
                'destacado' => '1',
                'promocion_titulo' => 'Semana del origen',
                'descuento' => 20,
                'promocion_inicia_at' => now()->toDateString(),
                'promocion_termina_at' => now()->addWeek()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $producto->refresh();

        $this->assertTrue($producto->promocionVigente());
        $this->assertSame(20, $producto->descuento);
        $this->assertSame('Semana del origen', $producto->promocion_titulo);
    }

    public function test_quitar_el_destacado_limpia_el_descuento_y_la_vigencia(): void
    {
        $producto = Producto::factory()->conStock(10)->enPromocion(30)->create();

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), ['descuento' => 30])
            ->assertSessionHasNoErrors();

        $producto->refresh();

        $this->assertFalse($producto->destacado);
        $this->assertSame(0, $producto->descuento);
        $this->assertNull($producto->promocion_termina_at);
    }

    public function test_la_vigencia_no_puede_terminar_antes_de_empezar(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), [
                'destacado' => '1',
                'descuento' => 10,
                'promocion_inicia_at' => now()->toDateString(),
                'promocion_termina_at' => now()->subWeek()->toDateString(),
            ])
            ->assertSessionHasErrors('promocion_termina_at', null, 'promocion-'.$producto->slug);

        $this->assertFalse($producto->fresh()->destacado);
    }

    public function test_solo_administracion_y_marketing_gestionan_las_promociones(): void
    {
        $producto = Producto::factory()->create();

        foreach ([Rol::Proveedor, Rol::Cliente, Rol::LogisticaAlmacen, Rol::ProduccionOperaciones] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get(route('promociones.index'))->assertForbidden();
            $this->actingAs($usuario)
                ->patch(route('promociones.update', $producto), ['destacado' => '1'])
                ->assertForbidden();
        }

        $this->actingAs($this->administrador())->get(route('promociones.index'))->assertOk();

        $marketing = User::factory()->conRol(Rol::MarketingVentas)->create();

        $this->actingAs($marketing)->get(route('promociones.index'))->assertOk();
        $this->actingAs($marketing)
            ->patch(route('promociones.update', $producto), ['destacado' => '1', 'descuento' => 10])
            ->assertRedirect();

        $this->assertTrue($producto->fresh()->destacado);
    }

    public function test_la_promocion_guarda_la_imagen_del_banner(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->create(['nombre' => 'Bourbon Salvador']);

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), [
                'destacado' => '1',
                'promocion_titulo' => 'Semana del café de origen',
                'descuento' => 15,
                'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 600),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $producto->refresh();

        $this->assertNotNull($producto->promocion_banner);
        $this->assertTrue($producto->tieneBanner());
        Storage::disk('public')->assertExists($producto->promocion_banner);
    }

    public function test_el_banner_nuevo_reemplaza_al_anterior_en_el_disco(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->enPromocion()->create();

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), [
                'destacado' => '1',
                'banner' => UploadedFile::fake()->image('primero.jpg'),
            ]);

        $primero = $producto->fresh()->promocion_banner;

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), [
                'destacado' => '1',
                'banner' => UploadedFile::fake()->image('segundo.jpg'),
            ]);

        $segundo = $producto->fresh()->promocion_banner;

        $this->assertNotSame($primero, $segundo);
        Storage::disk('public')->assertMissing($primero);
        Storage::disk('public')->assertExists($segundo);
    }

    public function test_el_banner_se_conserva_si_no_se_envia_una_imagen_nueva(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->enPromocion()->create();

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), [
                'destacado' => '1',
                'banner' => UploadedFile::fake()->image('banner.jpg'),
            ]);

        $banner = $producto->fresh()->promocion_banner;

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), ['destacado' => '1', 'descuento' => 30]);

        $this->assertSame($banner, $producto->fresh()->promocion_banner);
        Storage::disk('public')->assertExists($banner);
    }

    public function test_se_puede_quitar_el_banner_de_la_promocion(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->enPromocion()->create();

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), [
                'destacado' => '1',
                'banner' => UploadedFile::fake()->image('banner.jpg'),
            ]);

        $banner = $producto->fresh()->promocion_banner;

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), ['destacado' => '1', 'quitar_banner' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertNull($producto->fresh()->promocion_banner);
        Storage::disk('public')->assertMissing($banner);
    }

    public function test_el_banner_rechaza_archivos_que_no_son_imagen(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->create();

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), [
                'destacado' => '1',
                'banner' => UploadedFile::fake()->create('precios.pdf', 200, 'application/pdf'),
            ])
            ->assertSessionHasErrors('banner', null, 'promocion-'.$producto->slug);

        $this->assertNull($producto->fresh()->promocion_banner);
    }

    public function test_el_catalogo_muestra_el_banner_de_la_promocion_vigente(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->enPromocion()->create(['nombre' => 'Geisha Blend']);

        $this->actingAs($this->administrador())
            ->patch(route('promociones.update', $producto), [
                'destacado' => '1',
                'promocion_titulo' => 'Semana del café de origen',
                'descuento' => 20,
                'banner' => UploadedFile::fake()->image('banner.jpg'),
            ]);

        $this->actingAs($this->cliente())
            ->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('Semana del café de origen')
            ->assertSee(Storage::disk('public')->url($producto->fresh()->promocion_banner));
    }

    /**
     * @return array<string, string>
     */
    private function datosCliente(): array
    {
        return [
            'cliente_nombre' => 'Cafetería Andina',
            'cliente_telefono' => '945664313',
            'cliente_tipo' => 'Cafetería',
            'cliente_direccion' => 'Av. Ejército 401, Yanahuara',
            'tipo_entrega' => TipoEntrega::Delivery->value,
            'metodo_pago' => MetodoPago::Efectivo->value,
        ];
    }

    private function cliente(): User
    {
        return User::factory()->conRol(Rol::Cliente)->create();
    }

    private function administrador(): User
    {
        return User::factory()->conRol(Rol::Administrador)->create();
    }
}
