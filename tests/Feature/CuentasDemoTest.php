<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Las cuentas de demostración se definen una sola vez, en config/logicoffee.php,
 * y solo las usa el seeder: la pantalla de ingreso no publica credenciales.
 */
class CuentasDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_hay_una_cuenta_de_demostracion_por_cada_rol(): void
    {
        $roles = array_column(config('logicoffee.cuentas_demo'), 'rol');

        $this->assertEqualsCanonicalizing(
            array_column(Rol::cases(), 'value'),
            $roles,
        );
        $this->assertSameSize($roles, array_unique($roles));
    }

    public function test_el_seeder_crea_cada_cuenta_activa_y_con_su_rol(): void
    {
        $this->seed(UsuarioSeeder::class);

        $this->assertCount(count(Rol::cases()), User::all());

        foreach (config('logicoffee.cuentas_demo') as $cuenta) {
            $usuario = User::where('username', $cuenta['username'])->sole();

            $this->assertSame(Rol::from($cuenta['rol']), $usuario->rol);
            $this->assertTrue($usuario->activo);
            $this->assertTrue(Hash::check(config('logicoffee.password_demo'), $usuario->password));
        }
    }

    public function test_el_seeder_no_duplica_las_cuentas_al_volver_a_ejecutarse(): void
    {
        $this->seed(UsuarioSeeder::class);
        $this->seed(UsuarioSeeder::class);

        $this->assertCount(count(Rol::cases()), User::all());
    }

    public function test_cada_cuenta_entra_por_la_primera_seccion_de_su_rol(): void
    {
        $this->seed(UsuarioSeeder::class);

        foreach (config('logicoffee.cuentas_demo') as $cuenta) {
            $seccion = Rol::from($cuenta['rol'])->secciones()[0];

            Livewire::test('login')
                ->set('usuario', $cuenta['username'])
                ->set('password', config('logicoffee.password_demo'))
                ->call('entrar')
                ->assertRedirect(route($seccion->ruta()));

            Livewire::test('cerrar-sesion')->call('cerrar');
        }
    }

    public function test_la_pantalla_de_login_no_publica_las_credenciales(): void
    {
        $respuesta = $this->get(route('login'))->assertOk();

        $respuesta->assertDontSee(config('logicoffee.password_demo'));

        foreach (config('logicoffee.cuentas_demo') as $cuenta) {
            $respuesta->assertDontSee($cuenta['username']);
        }
    }
}
