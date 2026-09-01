<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $this->actingAs($this->administrador())
            ->post(route('productos.foto.update', $producto), [
                'foto' => UploadedFile::fake()->image('bourbon.jpg', 800, 600),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $producto->refresh();

        $this->assertNotNull($producto->imagen);
        Storage::disk('public')->assertExists($producto->imagen);
    }

    public function test_subir_una_foto_nueva_borra_la_anterior(): void
    {
        $producto = Producto::factory()->create();
        $administrador = $this->administrador();

        $this->actingAs($administrador)->post(route('productos.foto.update', $producto), [
            'foto' => UploadedFile::fake()->image('primera.jpg'),
        ]);

        $primera = $producto->fresh()->imagen;

        $this->actingAs($administrador)->post(route('productos.foto.update', $producto), [
            'foto' => UploadedFile::fake()->image('segunda.jpg'),
        ]);

        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertExists($producto->fresh()->imagen);
    }

    public function test_solo_se_aceptan_imagenes(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->administrador())
            ->post(route('productos.foto.update', $producto), [
                'foto' => UploadedFile::fake()->create('lista.pdf', 200, 'application/pdf'),
            ])
            ->assertSessionHasErrors('foto');

        $this->assertNull($producto->fresh()->imagen);
    }

    public function test_quitar_la_foto_la_borra_del_disco(): void
    {
        $producto = Producto::factory()->create();
        $administrador = $this->administrador();

        $this->actingAs($administrador)->post(route('productos.foto.update', $producto), [
            'foto' => UploadedFile::fake()->image('bourbon.jpg'),
        ]);

        $ruta = $producto->fresh()->imagen;

        $this->actingAs($administrador)
            ->delete(route('productos.foto.destroy', $producto))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($ruta);
        $this->assertNull($producto->fresh()->imagen);
    }

    public function test_el_cliente_no_sube_fotos(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs(User::factory()->conRol(Rol::Cliente)->create())
            ->post(route('productos.foto.update', $producto), [
                'foto' => UploadedFile::fake()->image('bourbon.jpg'),
            ])
            ->assertForbidden();

        $this->assertNull($producto->fresh()->imagen);
    }

    private function administrador(): User
    {
        return User::factory()->conRol(Rol::Administrador)->create();
    }
}
