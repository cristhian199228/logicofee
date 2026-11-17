<?php

namespace App\Support;

use Illuminate\Contracts\Config\Repository as Configuracion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Sube archivos a una biblioteca de documentos de SharePoint mediante
 * Microsoft Graph, autenticándose como aplicación (client credentials).
 */
class RepositorioSharePoint
{
    private const URL_GRAPH = 'https://graph.microsoft.com/v1.0';

    public function __construct(private Configuracion $configuracion) {}

    /**
     * Sube el archivo a la carpeta configurada y devuelve su URL en SharePoint.
     *
     * @throws RuntimeException si falta configuración o SharePoint rechaza la subida.
     */
    public function subir(string $rutaLocal, string $nombre): string
    {
        $sitio = $this->valor('site_id');
        $carpeta = trim($this->valor('carpeta'), '/');
        $destino = rawurlencode($carpeta).'/'.rawurlencode($nombre);

        $archivo = fopen($rutaLocal, 'rb');

        if ($archivo === false) {
            throw new RuntimeException("No se pudo abrir el archivo {$rutaLocal}.");
        }

        try {
            $respuesta = Http::withToken($this->tokenDeAcceso())
                ->connectTimeout(5)
                ->timeout(600)
                ->retry(3, 2000, fn (Throwable $e) => $e instanceof ConnectionException, throw: false)
                ->withBody($archivo, 'application/gzip')
                ->put(self::URL_GRAPH."/sites/{$sitio}/drive/root:/{$destino}:/content");
        } finally {
            if (is_resource($archivo)) {
                fclose($archivo);
            }
        }

        if (! $respuesta->successful()) {
            throw new RuntimeException('SharePoint rechazó la subida: HTTP '.$respuesta->status().' '.$respuesta->body());
        }

        return (string) $respuesta->json('webUrl', $destino);
    }

    /** Pide un token de aplicación a Microsoft Entra ID (OAuth 2.0 client credentials). */
    private function tokenDeAcceso(): string
    {
        $respuesta = Http::asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post('https://login.microsoftonline.com/'.$this->valor('tenant_id').'/oauth2/v2.0/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->valor('client_id'),
                'client_secret' => $this->valor('client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

        if (! $respuesta->successful() || $respuesta->json('access_token') === null) {
            throw new RuntimeException('Microsoft Entra ID no entregó un token: HTTP '.$respuesta->status());
        }

        return (string) $respuesta->json('access_token');
    }

    private function valor(string $clave): string
    {
        $valor = $this->configuracion->get("services.sharepoint.{$clave}");

        if ($valor === null || $valor === '') {
            throw new RuntimeException("Falta configurar services.sharepoint.{$clave} (variable SHAREPOINT_".strtoupper($clave).').');
        }

        return (string) $valor;
    }
}
