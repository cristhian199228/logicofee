<?php

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Controles de la estrategia de seguridad (sección 3.2 del informe) que no
 * cubren las pruebas de cada módulo.
 */
class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_cuentas_de_demostracion_el_login_no_las_ofrece(): void
    {
        config(['logicoffee.mostrar_cuentas_demo' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Cuentas de demostración')
            ->assertDontSee(config('logicoffee.password_demo'));

        Livewire::test('login')
            ->call('usarCuentaDemo', config('logicoffee.cuentas_demo')[0]['username'])
            ->assertSet('usuario', '')
            ->assertSet('password', '');
    }

    public function test_una_contrasena_sin_mayusculas_ni_numeros_se_rechaza(): void
    {
        Livewire::actingAs(User::factory()->conRol(Rol::Administrador)->create())
            ->test('usuarios')
            ->set('username', 'despacho')
            ->set('name', 'Ana Quispe')
            ->set('email', 'despacho@logicoffee.test')
            ->set('password', 'cafecafe')
            ->set('password_confirmation', 'cafecafe')
            ->set('rol', Rol::Proveedor->value)
            ->call('crear')
            ->assertHasErrors('password');

        $this->assertDatabaseMissing('users', ['username' => 'despacho']);
    }

    public function test_el_ingreso_fallido_queda_en_el_log_de_seguridad(): void
    {
        User::factory()->create(['username' => 'cliente', 'password' => 'demo1234']);

        Log::shouldReceive('channel')->with('seguridad')->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(
            fn (string $mensaje, array $contexto) => $mensaje === 'Ingreso fallido.'
                && $contexto['usuario'] === 'cliente'
                && $contexto['cuenta_existe'] === true
        );

        Livewire::test('login')
            ->set('usuario', 'cliente')
            ->set('password', 'incorrecta')
            ->call('entrar')
            ->assertHasErrors('usuario');
    }

    public function test_el_bloqueo_por_intentos_queda_en_el_log_de_seguridad(): void
    {
        User::factory()->create(['username' => 'cliente', 'password' => 'demo1234']);

        Log::shouldReceive('channel')->with('seguridad')->andReturnSelf();
        Log::shouldReceive('warning')->times(5);
        Log::shouldReceive('alert')->once()->withArgs(
            fn (string $mensaje) => $mensaje === 'Ingreso bloqueado por exceso de intentos.'
        );

        $login = Livewire::test('login')->set('usuario', 'cliente')->set('password', 'incorrecta');

        foreach (range(1, 6) as $intento) {
            $login->call('entrar');
        }

        $login->assertHasErrors('usuario');
        $this->assertGuest();
    }

    public function test_el_acceso_a_una_seccion_ajena_queda_en_el_log_de_seguridad(): void
    {
        $cliente = User::factory()->conRol(Rol::Cliente)->create(['username' => 'cliente']);

        Log::shouldReceive('channel')->with('seguridad')->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(
            fn (string $mensaje, array $contexto) => $mensaje === 'Acceso denegado a una sección.'
                && $contexto['usuario'] === 'cliente'
                && $contexto['seccion'] === 'usuarios'
        );

        $this->actingAs($cliente)->get(route('usuarios.index'))->assertForbidden();
    }

    public function test_las_respuestas_llevan_las_cabeceras_de_seguridad(): void
    {
        Vite::useHotFile(storage_path('framework/testing/sin-servidor-vite'));

        $politica = $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("connect-src 'self'", $politica);
        $this->assertStringContainsString("frame-ancestors 'none'", $politica);
    }

    public function test_hsts_solo_se_envia_por_https(): void
    {
        $this->get(route('login'))->assertHeaderMissing('Strict-Transport-Security');

        $this->get(str_replace('http://', 'https://', route('login')))
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_el_cambio_de_estado_de_un_pedido_queda_en_la_bitacora(): void
    {
        $pedido = Pedido::factory()->enEstado(EstadoPedido::Pendiente)->create();
        $encargado = User::factory()->conRol(Rol::LogisticaAlmacen)->create();

        $this->actingAs($encargado);
        $pedido->update(['estado' => EstadoPedido::Preparacion->value]);

        $auditoria = $pedido->auditorias()->where('accion', 'actualizado')->sole();

        $this->assertTrue($auditoria->usuario->is($encargado));
        $this->assertSame(
            ['antes' => EstadoPedido::Pendiente->value, 'despues' => EstadoPedido::Preparacion->value],
            $auditoria->cambios['estado'],
        );
    }

    public function test_la_bitacora_no_guarda_contrasenas(): void
    {
        $administrador = User::factory()->conRol(Rol::Administrador)->create();
        $usuario = User::factory()->conRol(Rol::Cliente)->create();

        Livewire::actingAs($administrador)
            ->test('usuario-editor', ['usuario' => $usuario])
            ->set('password', 'Cafe12345')
            ->set('password_confirmation', 'Cafe12345')
            ->call('guardar')
            ->assertHasNoErrors();

        $auditoria = $usuario->auditorias()->where('accion', 'actualizado')->sole();

        $this->assertTrue($auditoria->usuario->is($administrador));
        $this->assertSame('[modificada]', $auditoria->cambios['password']);
        $this->assertStringNotContainsString($usuario->fresh()->password, json_encode($auditoria->cambios));
        $this->assertSame('[registrada]', $usuario->auditorias()->where('accion', 'creado')->sole()->cambios['password']);
    }

    public function test_eliminar_un_pedido_queda_en_la_bitacora(): void
    {
        $pedido = Pedido::factory()->create();

        $pedido->delete();

        $this->assertDatabaseHas(Auditoria::class, [
            'auditable_type' => Pedido::class,
            'auditable_id' => $pedido->id,
            'accion' => 'eliminado',
        ]);
    }
}
