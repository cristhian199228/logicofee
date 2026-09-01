<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_de_login_se_muestra(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Iniciar Sesión');
    }

    public function test_el_usuario_entra_con_credenciales_validas(): void
    {
        $usuario = User::factory()->conRol(Rol::Cliente)->create([
            'username' => 'cliente',
            'password' => 'demo1234',
        ]);

        $this->post(route('login'), ['usuario' => 'cliente', 'password' => 'demo1234'])
            ->assertRedirect(route('catalogo.index'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_usuario_entra_por_la_primera_seccion_de_su_rol(): void
    {
        User::factory()->conRol(Rol::Proveedor)->create([
            'username' => 'proveedor',
            'password' => 'demo1234',
        ]);

        $this->post(route('login'), ['usuario' => 'proveedor', 'password' => 'demo1234'])
            ->assertRedirect(route('seguimiento.index'));
    }

    public function test_el_usuario_no_entra_con_una_contrasena_incorrecta(): void
    {
        User::factory()->create(['username' => 'cliente', 'password' => 'demo1234']);

        $this->post(route('login'), ['usuario' => 'cliente', 'password' => 'incorrecta'])
            ->assertSessionHasErrors('usuario');

        $this->assertGuest();
    }

    public function test_el_usuario_cierra_sesion(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_las_secciones_exigen_sesion_iniciada(): void
    {
        $this->get(route('catalogo.index'))->assertRedirect(route('login'));
    }
}
