<?php

namespace App\Support;

use App\Enums\EstadoPago;
use App\Enums\MetodoPago;
use Illuminate\Support\Str;

/**
 * Cobro simulado del pedido. No se conecta con ningún banco: aprueba la
 * operación y devuelve un código, salvo que el medio termine en los cuatro
 * dígitos de prueba con los que se demuestra un rechazo.
 */
class PasarelaPagoSimulada
{
    /** Tarjeta o celular terminado así: la pasarela siempre lo rechaza. */
    public const TERMINACION_RECHAZADA = '0000';

    /**
     * @param  array{tarjeta_numero?: ?string, tarjeta_titular?: ?string, yape_celular?: ?string}  $datos
     */
    public function cobrar(MetodoPago $metodo, array $datos, float $monto): ResultadoPago
    {
        return match ($metodo) {
            MetodoPago::Efectivo => $this->contraEntrega($monto),
            MetodoPago::Tarjeta => $this->conTarjeta((string) ($datos['tarjeta_numero'] ?? '')),
            MetodoPago::Yape => $this->conYape((string) ($datos['yape_celular'] ?? '')),
        };
    }

    /** El efectivo no se cobra ahora: queda pendiente hasta la entrega. */
    private function contraEntrega(float $monto): ResultadoPago
    {
        return new ResultadoPago(
            estado: EstadoPago::Pendiente,
            detalle: 'Se cobra al entregar: $'.number_format($monto, 2),
        );
    }

    private function conTarjeta(string $numero): ResultadoPago
    {
        $digitos = $this->soloDigitos($numero);
        $ultimos = Str::substr($digitos, -4);

        if ($ultimos === self::TERMINACION_RECHAZADA) {
            return new ResultadoPago(
                estado: EstadoPago::Rechazado,
                detalle: 'Tarjeta ****'.$ultimos,
                motivo: 'El banco rechazó la tarjeta por fondos insuficientes.',
            );
        }

        return new ResultadoPago(
            estado: EstadoPago::Pagado,
            referencia: 'AUT-'.Str::upper(Str::random(8)),
            detalle: 'Tarjeta ****'.$ultimos,
        );
    }

    private function conYape(string $celular): ResultadoPago
    {
        $digitos = $this->soloDigitos($celular);
        $ultimos = Str::substr($digitos, -4);

        if ($ultimos === self::TERMINACION_RECHAZADA) {
            return new ResultadoPago(
                estado: EstadoPago::Rechazado,
                detalle: 'Yape '.$this->enmascarar($digitos),
                motivo: 'El número no tiene una cuenta Yape activa.',
            );
        }

        return new ResultadoPago(
            estado: EstadoPago::Pagado,
            referencia: 'YPE-'.Str::upper(Str::random(8)),
            detalle: 'Yape '.$this->enmascarar($digitos),
        );
    }

    /** Deja a la vista solo los últimos cuatro dígitos del celular. */
    private function enmascarar(string $digitos): string
    {
        return Str::mask($digitos, '*', 0, max(Str::length($digitos) - 4, 0));
    }

    private function soloDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }
}
