<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_lista_las_cuentas_con_su_rol(): void
    {
        User::factory()->conRol(Rol::Proveedor)->create(['name' => 'C. Vargas', 'username' => 'proveedor']);

        $this->actingAs($this->administrador())
            ->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee('C. Vargas')
            ->assertSee('@proveedor')
            ->assertSee(Rol::Proveedor->value);
    }

    public function test_el_administrador_crea_una_cuenta_con_su_rol(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('usuarios.store'), [
                'username' => 'despacho',
                'name' => 'Ana Quispe',
                'email' => 'despacho@logicoffee.test',
                'password' => 'demo12345',
                'password_confirmation' => 'demo12345',
                'rol' => Rol::Proveedor->value,
                'descripcion' => 'Coordina la entrega de pedidos.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $usuario = User::where('username', 'despacho')->sole();

        $this->assertSame(Rol::Proveedor, $usuario->rol);
        $this->assertTrue($usuario->activo);
        $this->assertSame('AQ', $usuario->iniciales);
        $this->assertTrue(Hash::check('demo12345', $usuario->password));
    }

    public function test_el_usuario_no_se_repite(): void
    {
        User::factory()->create(['username' => 'despacho']);

        $this->actingAs($this->administrador())
            ->post(route('usuarios.store'), [
                'username' => 'despacho',
                'name' => 'Ana Quispe',
                'email' => 'otro@logicoffee.test',
                'password' => 'demo12345',
                'password_confirmation' => 'demo12345',
                'rol' => Rol::Cliente->value,
            ])
            ->assertSessionHasErrors('username');

        $this->assertDatabaseCount('users', 2);
    }

    public function test_el_administrador_cambia_el_rol_de_otra_cuenta(): void
    {
        $usuario = User::factory()->conRol(Rol::Cliente)->create(['name' => 'Ana Quispe']);

        $this->actingAs($this->administrador())
            ->patch(route('usuarios.update', $usuario), [
                'name' => 'Ana Quispe',
                'email' => $usuario->email,
                'rol' => Rol::Proveedor->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Rol::Proveedor, $usuario->fresh()->rol);
    }

    public function test_nadie_cambia_su_propio_rol(): void
    {
        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->patch(route('usuarios.update', $administrador), [
                'name' => $administrador->name,
                'email' => $administrador->email,
                'rol' => Rol::Cliente->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Rol::Administrador, $administrador->fresh()->rol);
    }

    public function test_desactivar_una_cuenta_le_cierra_el_acceso(): void
    {
        $usuario = User::factory()->conRol(Rol::Cliente)->create([
            'username' => 'cliente',
            'password' => 'demo1234',
        ]);

        $this->actingAs($this->administrador())
            ->patch(route('usuarios.estado.update', $usuario))
            ->assertSessionHasNoErrors();

        $this->assertFalse($usuario->fresh()->activo);

        // El administrador cierra su sesión antes de probar el acceso del cliente.
        $this->post(route('logout'));

        $this->post(route('login'), ['usuario' => 'cliente', 'password' => 'demo1234'])
            ->assertSessionHasErrors('usuario');

        $this->assertGuest();
    }

    public function test_la_sesion_abierta_de_una_cuenta_desactivada_se_corta(): void
    {
        $usuario = User::factory()->conRol(Rol::Cliente)->inactivo()->create();

        $this->actingAs($usuario)
            ->get(route('catalogo.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_el_administrador_no_se_desactiva_a_si_mismo(): void
    {
        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->patch(route('usuarios.estado.update', $administrador))
            ->assertSessionHas('aviso', 'No puedes desactivar tu propia cuenta.');

        $this->assertTrue($administrador->fresh()->activo);
    }

    public function test_solo_el_administrador_gestiona_las_cuentas(): void
    {
        $usuario = User::factory()->create();

        foreach ([Rol::Proveedor, Rol::Cliente] as $rol) {
            $otro = User::factory()->conRol($rol)->create();

            $this->actingAs($otro)->get(route('usuarios.index'))->assertForbidden();
            $this->actingAs($otro)
                ->patch(route('usuarios.estado.update', $usuario))
                ->assertForbidden();
        }

        $this->assertTrue($usuario->fresh()->activo);
    }

    private function administrador(): User
    {
        return User::factory()->conRol(Rol::Administrador)->create();
    }
}
