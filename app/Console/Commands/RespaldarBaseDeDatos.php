<?php

namespace App\Console\Commands;

use App\Support\RepositorioSharePoint;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Throwable;

/**
 * Respaldo diario de la base de datos: genera un volcado comprimido con
 * mysqldump desde el primario y lo sube a la carpeta de respaldos en
 * SharePoint. Se programa en routes/console.php todos los días a las 11:00.
 */
#[Signature('respaldo:base-de-datos {--sin-subir : Solo genera el volcado local, sin enviarlo a SharePoint}')]
#[Description('Genera un volcado comprimido de la base de datos y lo sube a SharePoint.')]
class RespaldarBaseDeDatos extends Command
{
    private const CARPETA = 'respaldos';

    public function handle(RepositorioSharePoint $sharepoint): int
    {
        $disco = Storage::disk('local');
        $disco->makeDirectory(self::CARPETA);

        $nombre = 'logicoffee-'.now()->format('Y-m-d_His').'.sql.gz';
        $ruta = $disco->path(self::CARPETA.'/'.$nombre);

        if (! $this->generarVolcado($ruta)) {
            return self::FAILURE;
        }

        $this->info('Volcado generado: '.self::CARPETA."/{$nombre} (".Number::fileSize(filesize($ruta)).')');

        if (! $this->option('sin-subir')) {
            try {
                $url = $sharepoint->subir($ruta, $nombre);
            } catch (Throwable $e) {
                Log::error('No se pudo subir el respaldo a SharePoint.', ['archivo' => $nombre, 'error' => $e->getMessage()]);
                $this->error('No se pudo subir a SharePoint: '.$e->getMessage());

                return self::FAILURE;
            }

            $this->info("Subido a SharePoint: {$url}");
        }

        $this->depurarRespaldosLocales($disco);

        return self::SUCCESS;
    }

    /**
     * Ejecuta mysqldump contra el primario (conexión de escritura) y comprime
     * el resultado. Se ejecutan como dos procesos separados, sin tubería, para
     * que un fallo de mysqldump no quede oculto por el código de salida de gzip.
     */
    private function generarVolcado(string $ruta): bool
    {
        $conexion = config('database.connections.'.config('database.default'));
        $servidor = $conexion['write']['host'][0] ?? $conexion['host'];
        $rutaSql = substr($ruta, 0, -strlen('.gz'));

        $volcado = Process::env(['MYSQL_PWD' => (string) $conexion['password']])
            ->timeout(600)
            ->run([
                'mysqldump',
                '--host='.$servidor,
                '--port='.$conexion['port'],
                '--user='.$conexion['username'],
                '--single-transaction',
                '--set-gtid-purged=OFF',
                '--no-tablespaces',
                '--routines',
                '--triggers',
                '--events',
                '--result-file='.$rutaSql,
                $conexion['database'],
            ]);

        if ($volcado->failed()) {
            @unlink($rutaSql);
            Log::error('Falló la generación del volcado de la base de datos.', ['salida' => $volcado->errorOutput()]);
            $this->error('No se pudo generar el volcado: '.trim($volcado->errorOutput()));

            return false;
        }

        $compresion = Process::timeout(300)->run(['gzip', '-f', $rutaSql]);

        if ($compresion->failed() || ! is_file($ruta) || filesize($ruta) === 0) {
            Log::error('Falló la compresión del volcado de la base de datos.', ['salida' => $compresion->errorOutput()]);
            $this->error('No se pudo comprimir el volcado: '.trim($compresion->errorOutput()));

            return false;
        }

        return true;
    }

    /** Conserva localmente solo los respaldos de los últimos días configurados. */
    private function depurarRespaldosLocales(Filesystem $disco): void
    {
        $limite = now()->subDays((int) config('services.sharepoint.dias_locales', 7))->getTimestamp();

        foreach ($disco->files(self::CARPETA) as $archivo) {
            if ($disco->lastModified($archivo) < $limite) {
                $disco->delete($archivo);
                $this->line("Respaldo local antiguo eliminado: {$archivo}");
            }
        }
    }
}
