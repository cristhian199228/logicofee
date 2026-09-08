<?php

namespace Tests\Feature;

use App\Enums\CategoriaProducto;
use App\Enums\Rol;
use App\Models\Lote;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_la_pantalla_lista_el_catalogo_con_su_stock(): void
    {
        Producto::factory()->conStock(40)->create(['nombre' => 'Bourbon Salvador']);
        Producto::factory()->agotado()->create(['nombre' => 'Mocha Espresso']);

        $pantalla = Livewire::actingAs($this->marketing())
            ->test('productos')
            ->assertSee('Bourbon Salvador')
            ->assertSee('Mocha Espresso');

        $this->assertSame(1, $pantalla->instance()->bajoStock);
    }

    public function test_marketing_agrega_un_cafe_al_catalogo(): void
    {
        $this->altaDeProducto()
            ->set('foto', UploadedFile::fake()->image('geisha.jpg', 800, 600))
            ->call('agregar')
            ->assertHasNoErrors();

        $producto = Producto::firstWhere('nombre', 'Geisha Blend Premium');

        $this->assertSame('geisha-blend-premium-500-g', $producto->slug);
        $this->assertSame(CategoriaProducto::Blends, $producto->categoria);
        $this->assertSame('24.90', $producto->precio);
        $this->assertSame(0, $producto->stock);
        $this->assertSame(12, $producto->stock_minimo);
        Storage::disk('public')->assertExists($producto->imagen);
    }

    public function test_dos_cafes_con_el_mismo_nombre_no_comparten_direccion(): void
    {
        $alta = $this->altaDeProducto();

        $alta->call('agregar')->assertHasNoErrors();

        $this->altaDeProducto()->call('agregar')->assertHasNoErrors();

        $this->assertSame(
            ['geisha-blend-premium-500-g', 'geisha-blend-premium-500-g-2'],
            Producto::query()->orderBy('id')->pluck('slug')->all(),
        );
    }

    public function test_el_alta_valida_los_datos_del_cafe(): void
    {
        Livewire::actingAs($this->marketing())
            ->test('productos')
            ->set('nombre', '')
            ->set('presentacion', '250 g')
            ->set('categoria', 'Instantáneo')
            ->set('descripcion', 'Notas de cacao.')
            ->set('precio', 0)
            ->set('stock_minimo', -1)
            ->set('acento', 'verde')
            ->call('agregar')
            ->assertHasErrors(['nombre', 'categoria', 'precio', 'stock_minimo', 'acento']);

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_marketing_edita_los_datos_de_un_cafe(): void
    {
        $producto = Producto::factory()->conStock(20)->create([
            'nombre' => 'Bourbon Salvador',
            'precio' => 18.00,
            'stock_minimo' => 10,
        ]);

        $direccion = $producto->slug;

        Livewire::actingAs($this->marketing())
            ->test('producto-editor', ['producto' => $producto])
            ->set('nombre', 'Bourbon Salvador Reserva')
            ->set('precio', 21.50)
            ->set('stock_minimo', 25)
            ->call('guardar')
            ->assertHasNoErrors();

        $producto->refresh();

        $this->assertSame('Bourbon Salvador Reserva', $producto->nombre);
        $this->assertSame('21.50', $producto->precio);
        $this->assertSame(25, $producto->stock_minimo);
        // La dirección no cambia: el catálogo y los carritos ya la usan.
        $this->assertSame($direccion, $producto->slug);
        // El stock sigue saliendo de los lotes.
        $this->assertSame(20, $producto->stock);
    }

    public function test_la_foto_nueva_reemplaza_a_la_anterior_al_editar(): void
    {
        $producto = Producto::factory()->create();

        $editor = Livewire::actingAs($this->marketing())->test('producto-editor', ['producto' => $producto]);

        $editor->set('foto', UploadedFile::fake()->image('primera.jpg'))->call('guardar');

        $primera = $producto->fresh()->imagen;

        $editor->set('foto', UploadedFile::fake()->image('segunda.jpg'))->call('guardar');

        $segunda = $producto->fresh()->imagen;

        $this->assertNotSame($primera, $segunda);
        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertExists($segunda);
    }

    public function test_la_foto_se_conserva_si_la_edicion_no_envia_una_nueva(): void
    {
        $producto = Producto::factory()->create();

        $editor = Livewire::actingAs($this->marketing())->test('producto-editor', ['producto' => $producto]);

        $editor->set('foto', UploadedFile::fake()->image('unica.jpg'))->call('guardar');

        $imagen = $producto->fresh()->imagen;

        $editor->set('nombre', 'Bourbon Salvador Reserva')->call('guardar');

        $this->assertSame($imagen, $producto->fresh()->imagen);
        Storage::disk('public')->assertExists($imagen);
    }

    public function test_se_retira_del_catalogo_un_cafe_sin_stock(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Mocha Espresso']);
        PedidoLinea::factory()->deProducto($producto, 3)->create();

        Livewire::actingAs($this->marketing())
            ->test('productos')
            ->call('retirar', $producto->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
        // El historial del pedido conserva su copia de los datos.
        $this->assertDatabaseHas('pedido_lineas', ['nombre' => 'Mocha Espresso', 'producto_id' => null]);
    }

    public function test_no_se_retira_un_cafe_que_todavia_tiene_stock(): void
    {
        $producto = Producto::factory()->conStock(15)->create();

        Livewire::actingAs($this->marketing())
            ->test('productos')
            ->call('retirar', $producto->id)
            ->assertSet('productoConError', $producto->id)
            ->assertSee('todavía tiene 15 uds en almacén');

        $this->assertDatabaseHas('productos', ['id' => $producto->id]);
        $this->assertSame(1, Lote::query()->count());
    }

    public function test_solo_quien_edita_el_catalogo_gestiona_los_productos(): void
    {
        $producto = Producto::factory()->create();

        foreach ([Rol::Administrador, Rol::Proveedor, Rol::MarketingVentas] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('productos.index'))
                ->assertOk();
        }

        foreach ([Rol::Cliente, Rol::LogisticaAlmacen, Rol::ProduccionOperaciones, Rol::DireccionGeneral] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get(route('productos.index'))->assertForbidden();

            Livewire::actingAs($usuario)->test('productos')->call('agregar')->assertForbidden();
            Livewire::actingAs($usuario)->test('productos')->call('retirar', $producto->id)->assertForbidden();
            Livewire::actingAs($usuario)
                ->test('producto-editor', ['producto' => $producto])
                ->call('guardar')
                ->assertForbidden();
        }

        $this->assertDatabaseCount('productos', 1);
    }

    /**
     * Alta de producto con los datos del formulario ya completados.
     */
    private function altaDeProducto(): Testable
    {
        return Livewire::actingAs($this->marketing())
            ->test('productos')
            ->set('nombre', 'Geisha Blend Premium')
            ->set('presentacion', '500 g')
            ->set('categoria', CategoriaProducto::Blends->value)
            ->set('descripcion', 'Notas florales con final a panela.')
            ->set('precio', 24.90)
            ->set('stock_minimo', 12)
            ->set('acento', '#4a7c3f');
    }

    private function marketing(): User
    {
        return User::factory()->conRol(Rol::MarketingVentas)->create();
    }
}
