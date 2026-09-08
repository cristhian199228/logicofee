<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        Livewire::test('login')
            ->set('usuario', 'cliente')
            ->set('password', 'demo1234')
            ->call('entrar')
            ->assertRedirect(route('catalogo.index'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_usuario_entra_por_la_primera_seccion_de_su_rol(): void
    {
        User::factory()->conRol(Rol::Proveedor)->create([
            'username' => 'proveedor',
            'password' => 'demo1234',
        ]);

        Livewire::test('login')
            ->set('usuario', 'proveedor')
            ->set('password', 'demo1234')
            ->call('entrar')
            ->assertRedirect(route('seguimiento.index'));
    }

    public function test_el_usuario_no_entra_con_una_contrasena_incorrecta(): void
    {
        User::factory()->create(['username' => 'cliente', 'password' => 'demo1234']);

        Livewire::test('login')
            ->set('usuario', 'cliente')
            ->set('password', 'incorrecta')
            ->call('entrar')
            ->assertHasErrors('usuario');

        $this->assertGuest();
    }

    public function test_una_cuenta_desactivada_no_entra(): void
    {
        User::factory()->create([
            'username' => 'cliente',
            'password' => 'demo1234',
            'activo' => false,
        ]);

        Livewire::test('login')
            ->set('usuario', 'cliente')
            ->set('password', 'demo1234')
            ->call('entrar')
            ->assertHasErrors('usuario');

        $this->assertGuest();
    }

    public function test_el_usuario_cierra_sesion(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test('cerrar-sesion')
            ->call('cerrar')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_las_secciones_exigen_sesion_iniciada(): void
    {
        $this->get(route('catalogo.index'))->assertRedirect(route('login'));
    }
}
