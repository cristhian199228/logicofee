<?php

namespace Tests\Feature;

use App\Enums\MetodoPago;
use App\Enums\Rol;
use App\Enums\TipoEntrega;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Support\Carrito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
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

        $this->assertEquals(15.00, $producto->precioVigente());

        app(Carrito::class)->agregar($producto, 2);

        Livewire::actingAs($this->cliente())
            ->test('pedido-registrar')
            ->set('cliente_nombre', 'Cafetería Andina')
            ->set('cliente_telefono', '945664313')
            ->set('cliente_tipo', 'Cafetería')
            ->set('cliente_direccion', 'Av. Ejército 401, Yanahuara')
            ->set('tipo_entrega', TipoEntrega::Delivery->value)
            ->set('metodo_pago', MetodoPago::Efectivo->value)
            ->call('registrar')
            ->assertHasNoErrors();

        $pedido = Pedido::sole();

        $this->assertEquals(30.00, $pedido->subtotal);
        $this->assertEquals(15.00, $pedido->lineas->sole()->precio);
    }

    public function test_el_administrador_destaca_un_producto_con_vigencia(): void
    {
        $producto = Producto::factory()->conStock(10)->create();

        $this->editor($producto)
            ->set('destacado', true)
            ->set('promocion_titulo', 'Semana del origen')
            ->set('descuento', 20)
            ->set('promocion_inicia_at', now()->toDateString())
            ->set('promocion_termina_at', now()->addWeek()->toDateString())
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('promociones-actualizadas');

        $producto->refresh();

        $this->assertTrue($producto->promocionVigente());
        $this->assertSame(20, $producto->descuento);
        $this->assertSame('Semana del origen', $producto->promocion_titulo);
    }

    public function test_quitar_el_destacado_limpia_el_descuento_y_la_vigencia(): void
    {
        $producto = Producto::factory()->conStock(10)->enPromocion(30)->create();

        $this->editor($producto)
            ->set('destacado', false)
            ->call('guardar')
            ->assertHasNoErrors();

        $producto->refresh();

        $this->assertFalse($producto->destacado);
        $this->assertSame(0, $producto->descuento);
        $this->assertNull($producto->promocion_termina_at);
    }

    public function test_la_vigencia_no_puede_terminar_antes_de_empezar(): void
    {
        $producto = Producto::factory()->create();

        $this->editor($producto)
            ->set('destacado', true)
            ->set('descuento', 10)
            ->set('promocion_inicia_at', now()->toDateString())
            ->set('promocion_termina_at', now()->subWeek()->toDateString())
            ->call('guardar')
            ->assertHasErrors('promocion_termina_at');

        $this->assertFalse($producto->fresh()->destacado);
    }

    public function test_solo_administracion_y_marketing_gestionan_las_promociones(): void
    {
        $producto = Producto::factory()->create();

        foreach ([Rol::Proveedor, Rol::Cliente, Rol::LogisticaAlmacen, Rol::ProduccionOperaciones] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get(route('promociones.index'))->assertForbidden();

            Livewire::actingAs($usuario)
                ->test('promocion-editor', ['producto' => $producto])
                ->set('destacado', true)
                ->call('guardar')
                ->assertForbidden();
        }

        $this->actingAs($this->administrador())->get(route('promociones.index'))->assertOk();

        $marketing = User::factory()->conRol(Rol::MarketingVentas)->create();

        $this->actingAs($marketing)->get(route('promociones.index'))->assertOk();

        Livewire::actingAs($marketing)
            ->test('promocion-editor', ['producto' => $producto])
            ->set('destacado', true)
            ->set('descuento', 10)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertTrue($producto->fresh()->destacado);
    }

    public function test_la_promocion_guarda_la_imagen_del_banner(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->create(['nombre' => 'Bourbon Salvador']);

        $this->editor($producto)
            ->set('destacado', true)
            ->set('promocion_titulo', 'Semana del café de origen')
            ->set('descuento', 15)
            ->set('banner', UploadedFile::fake()->image('banner.jpg', 1200, 600))
            ->call('guardar')
            ->assertHasNoErrors();

        $producto->refresh();

        $this->assertNotNull($producto->promocion_banner);
        $this->assertTrue($producto->tieneBanner());
        Storage::disk('public')->assertExists($producto->promocion_banner);
    }

    public function test_el_banner_nuevo_reemplaza_al_anterior_en_el_disco(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->enPromocion()->create();
        $editor = $this->editor($producto)->set('destacado', true);

        $editor->set('banner', UploadedFile::fake()->image('primero.jpg'))->call('guardar');

        $primero = $producto->fresh()->promocion_banner;

        $editor->set('banner', UploadedFile::fake()->image('segundo.jpg'))->call('guardar');

        $segundo = $producto->fresh()->promocion_banner;

        $this->assertNotSame($primero, $segundo);
        Storage::disk('public')->assertMissing($primero);
        Storage::disk('public')->assertExists($segundo);
    }

    public function test_el_banner_se_conserva_si_no_se_envia_una_imagen_nueva(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->enPromocion()->create();
        $editor = $this->editor($producto)->set('destacado', true);

        $editor->set('banner', UploadedFile::fake()->image('banner.jpg'))->call('guardar');

        $banner = $producto->fresh()->promocion_banner;

        $editor->set('descuento', 30)->call('guardar');

        $this->assertSame($banner, $producto->fresh()->promocion_banner);
        Storage::disk('public')->assertExists($banner);
    }

    public function test_se_puede_quitar_el_banner_de_la_promocion(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->enPromocion()->create();
        $editor = $this->editor($producto)->set('destacado', true);

        $editor->set('banner', UploadedFile::fake()->image('banner.jpg'))->call('guardar');

        $banner = $producto->fresh()->promocion_banner;

        $editor->set('quitar_banner', true)->call('guardar')->assertHasNoErrors();

        $this->assertNull($producto->fresh()->promocion_banner);
        Storage::disk('public')->assertMissing($banner);
    }

    public function test_el_banner_rechaza_archivos_que_no_son_imagen(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->create();

        $this->editor($producto)
            ->set('destacado', true)
            ->set('banner', UploadedFile::fake()->create('precios.pdf', 200, 'application/pdf'))
            ->call('guardar')
            ->assertHasErrors('banner');

        $this->assertNull($producto->fresh()->promocion_banner);
    }

    public function test_el_catalogo_muestra_el_banner_de_la_promocion_vigente(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->enPromocion()->create(['nombre' => 'Geisha Blend']);

        $this->editor($producto)
            ->set('destacado', true)
            ->set('promocion_titulo', 'Semana del café de origen')
            ->set('descuento', 20)
            ->set('banner', UploadedFile::fake()->image('banner.jpg'))
            ->call('guardar');

        $this->actingAs($this->cliente())
            ->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('Semana del café de origen')
            ->assertSee(Storage::disk('public')->url($producto->fresh()->promocion_banner));
    }

    private function editor(Producto $producto): Testable
    {
        return Livewire::actingAs($this->administrador())
            ->test('promocion-editor', ['producto' => $producto]);
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
