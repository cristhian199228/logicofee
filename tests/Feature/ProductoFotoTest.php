<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoFotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_el_administrador_sube_la_foto_de_un_producto(): void
    {
        $producto = Producto::factory()->create();

        Livewire::actingAs($this->administrador())
            ->test('producto-tarjeta', ['producto' => $producto])
            ->set('foto', UploadedFile::fake()->image('bourbon.jpg', 800, 600))
            ->call('subirFoto')
            ->assertHasNoErrors();

        $producto->refresh();

        $this->assertNotNull($producto->imagen);
        Storage::disk('public')->assertExists($producto->imagen);
    }

    public function test_subir_una_foto_nueva_borra_la_anterior(): void
    {
        $producto = Producto::factory()->create();

        $tarjeta = Livewire::actingAs($this->administrador())
            ->test('producto-tarjeta', ['producto' => $producto]);

        $tarjeta->set('foto', UploadedFile::fake()->image('primera.jpg'))->call('subirFoto');

        $primera = $producto->fresh()->imagen;

        $tarjeta->set('foto', UploadedFile::fake()->image('segunda.jpg'))->call('subirFoto');

        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertExists($producto->fresh()->imagen);
    }

    public function test_solo_se_aceptan_imagenes(): void
    {
        $producto = Producto::factory()->create();

        Livewire::actingAs($this->administrador())
            ->test('producto-tarjeta', ['producto' => $producto])
            ->set('foto', UploadedFile::fake()->create('lista.pdf', 200, 'application/pdf'))
            ->call('subirFoto')
            ->assertHasErrors('foto');

        $this->assertNull($producto->fresh()->imagen);
    }

    public function test_quitar_la_foto_la_borra_del_disco(): void
    {
        $producto = Producto::factory()->create();

        $tarjeta = Livewire::actingAs($this->administrador())
            ->test('producto-tarjeta', ['producto' => $producto]);

        $tarjeta->set('foto', UploadedFile::fake()->image('bourbon.jpg'))->call('subirFoto');

        $ruta = $producto->fresh()->imagen;

        $tarjeta->call('quitarFoto');

        Storage::disk('public')->assertMissing($ruta);
        $this->assertNull($producto->fresh()->imagen);
    }

    public function test_el_cliente_no_sube_fotos(): void
    {
        $producto = Producto::factory()->create();

        Livewire::actingAs(User::factory()->conRol(Rol::Cliente)->create())
            ->test('producto-tarjeta', ['producto' => $producto])
            ->set('foto', UploadedFile::fake()->image('bourbon.jpg'))
            ->call('subirFoto')
            ->assertForbidden();

        $this->assertNull($producto->fresh()->imagen);
    }

    private function administrador(): User
    {
        return User::factory()->conRol(Rol::Administrador)->create();
    }
}
