<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RespaldarBaseDeDatosTest extends TestCase
{
    private const URL_TOKEN = 'https://login.microsoftonline.com/tenant-demo/oauth2/v2.0/token';

    private const URL_SUBIDA = 'https://graph.microsoft.com/v1.0/sites/sitio-demo/drive/root:/Respaldos%20LogiCoffee/logicoffee-2026-10-06_110000.sql.gz:/content';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sharepoint', [
            'tenant_id' => 'tenant-demo',
            'client_id' => 'cliente-demo',
            'client_secret' => 'secreto-demo',
            'site_id' => 'sitio-demo',
            'carpeta' => 'Respaldos LogiCoffee',
            'dias_locales' => 7,
        ]);
    }

    public function test_genera_el_volcado_del_primario_y_lo_sube_a_sharepoint(): void
    {
        $this->travelTo('2026-10-06 11:00:00');
        Storage::fake('local');
        $this->fingirMysqldump();
        Http::preventStrayRequests();
        Http::fake([
            self::URL_TOKEN => Http::response(['access_token' => 'token-demo']),
            self::URL_SUBIDA => Http::response(['webUrl' => 'https://utp.sharepoint.com/sites/LogiCoffee/respaldo.sql.gz'], 201),
        ]);

        $this->artisan('respaldo:base-de-datos')
            ->expectsOutputToContain('Volcado generado: respaldos/logicoffee-2026-10-06_110000.sql.gz')
            ->expectsOutputToContain('Subido a SharePoint: https://utp.sharepoint.com/sites/LogiCoffee/respaldo.sql.gz')
            ->assertSuccessful();

        Storage::disk('local')->assertExists('respaldos/logicoffee-2026-10-06_110000.sql.gz');

        $conexion = config('database.connections.'.config('database.default'));
        Process::assertRan(fn (PendingProcess $proceso) => $proceso->command[0] === 'mysqldump'
            && in_array('--host='.($conexion['write']['host'][0] ?? $conexion['host']), $proceso->command, true)
            && in_array('--single-transaction', $proceso->command, true)
            && end($proceso->command) === $conexion['database']
            && $proceso->environment['MYSQL_PWD'] === (string) $conexion['password']);
        Process::assertRan(fn (PendingProcess $proceso) => $proceso->command[0] === 'gzip');

        Http::assertSent(fn (Request $peticion) => $peticion->url() === self::URL_TOKEN
            && $peticion['grant_type'] === 'client_credentials'
            && $peticion['client_id'] === 'cliente-demo');
        Http::assertSent(fn (Request $peticion) => $peticion->url() === self::URL_SUBIDA
            && $peticion->method() === 'PUT'
            && $peticion->hasHeader('Authorization', 'Bearer token-demo'));
    }

    public function test_no_sube_nada_cuando_mysqldump_falla(): void
    {
        Storage::fake('local');
        $this->fingirMysqldump(exitoso: false);
        Http::fake();

        $this->artisan('respaldo:base-de-datos')
            ->expectsOutputToContain('No se pudo generar el volcado: mysqldump: Got error: 1045')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_falla_cuando_sharepoint_rechaza_la_subida(): void
    {
        $this->travelTo('2026-10-06 11:00:00');
        Storage::fake('local');
        $this->fingirMysqldump();
        Http::preventStrayRequests();
        Http::fake([
            self::URL_TOKEN => Http::response(['access_token' => 'token-demo']),
            self::URL_SUBIDA => Http::response(['error' => ['message' => 'Access denied']], 403),
        ]);

        $this->artisan('respaldo:base-de-datos')
            ->expectsOutputToContain('No se pudo subir a SharePoint: SharePoint rechazó la subida: HTTP 403')
            ->assertFailed();

        Storage::disk('local')->assertExists('respaldos/logicoffee-2026-10-06_110000.sql.gz');
    }

    public function test_la_opcion_sin_subir_omite_sharepoint_y_elimina_los_respaldos_locales_antiguos(): void
    {
        $this->travelTo('2026-10-06 11:00:00');
        Storage::fake('local');
        $this->fingirMysqldump();
        Http::fake();

        $disco = Storage::disk('local');
        $disco->put('respaldos/logicoffee-2026-09-20_110000.sql.gz', 'antiguo');
        touch($disco->path('respaldos/logicoffee-2026-09-20_110000.sql.gz'), now()->subDays(16)->getTimestamp());
        $disco->put('respaldos/logicoffee-2026-10-03_110000.sql.gz', 'reciente');

        $this->artisan('respaldo:base-de-datos --sin-subir')
            ->expectsOutputToContain('Respaldo local antiguo eliminado: respaldos/logicoffee-2026-09-20_110000.sql.gz')
            ->assertSuccessful();

        Http::assertNothingSent();
        $disco->assertMissing('respaldos/logicoffee-2026-09-20_110000.sql.gz');
        $disco->assertExists('respaldos/logicoffee-2026-10-03_110000.sql.gz');
        $disco->assertExists('respaldos/logicoffee-2026-10-06_110000.sql.gz');
    }

    public function test_el_respaldo_esta_programado_todos_los_dias_a_las_11_hora_de_lima(): void
    {
        $this->app->make(Kernel::class)->bootstrap();

        $evento = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($evento) => str_contains((string) $evento->command, 'respaldo:base-de-datos'));

        $this->assertNotNull($evento, 'El comando respaldo:base-de-datos no está programado.');
        $this->assertSame('0 11 * * *', $evento->expression);
        $this->assertSame('America/Lima', $evento->timezone);
    }

    /** Simula mysqldump y gzip: crea el archivo de salida indicado en cada comando y devuelve el código de salida. */
    private function fingirMysqldump(bool $exitoso = true): void
    {
        Process::fake(function (PendingProcess $proceso) use ($exitoso) {
            $comando = $proceso->command;

            if ($comando[0] === 'gzip') {
                file_put_contents($comando[2].'.gz', 'volcado comprimido');
                unlink($comando[2]);

                return Process::result();
            }

            if (! $exitoso) {
                return Process::result(errorOutput: "mysqldump: Got error: 1045: Access denied for user 'sail'@'%'", exitCode: 2);
            }

            $rutaSql = substr(collect($comando)->first(fn ($arg) => str_starts_with($arg, '--result-file=')), strlen('--result-file='));
            file_put_contents($rutaSql, '-- MySQL dump');

            return Process::result();
        });
    }
}
