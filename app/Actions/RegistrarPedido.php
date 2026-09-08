<?php

namespace App\Actions;

use App\Enums\EstadoPedido;
use App\Enums\MetodoPago;
use App\Enums\TipoEntrega;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Support\Carrito;
use App\Support\PasarelaPagoSimulada;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra el pedido con estado inicial "Pendiente", cobra según la forma de
 * pago elegida y consume los lotes (HU02).
 */
class RegistrarPedido
{
    /** Datos del cliente que viajan tal cual al pedido. */
    private const CAMPOS_CLIENTE = [
        'cliente_nombre', 'cliente_telefono', 'cliente_correo',
        'cliente_tipo', 'cliente_direccion', 'observaciones',
    ];

    public function __construct(private Carrito $carrito, private PasarelaPagoSimulada $pasarela) {}

    /**
     * @param  array<string, mixed>  $datos  Datos del cliente, forma de entrega y medio de pago.
     *
     * @throws ValidationException si otro pedido agotó el stock o si la pasarela rechaza el cobro.
     */
    public function handle(array $datos, User $usuario): Pedido
    {
        $entrega = TipoEntrega::from($datos['tipo_entrega']);
        $metodo = MetodoPago::from($datos['metodo_pago']);
        $datosCliente = Arr::only($datos, self::CAMPOS_CLIENTE);

        $lineas = $this->carrito->lineas();

        if ($lineas->isEmpty()) {
            throw ValidationException::withMessages([
                'carrito' => 'Agrega al menos un producto antes de registrar el pedido.',
            ]);
        }

        $pedido = DB::transaction(function () use ($lineas, $datos, $datosCliente, $entrega, $metodo, $usuario): Pedido {
            $productos = Producto::whereKey($lineas->pluck('producto.id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // El precio del pedido es el vigente al registrarlo: si el producto
            // está en promoción se cobra ya con el descuento aplicado (HU03).
            $subtotal = $lineas->sum(
                fn (array $linea) => $productos->get($linea['producto']->id)->precioVigente() * $linea['cantidad']
            );

            // El recojo en tienda no paga envío.
            $envio = $entrega->costoEnvio();
            $total = $subtotal + $envio;

            // Un cobro rechazado deja el pedido sin registrar: la transacción
            // revierte y el cliente puede corregir el medio de pago.
            $cobro = $this->pasarela->cobrar($metodo, $datos, $total);

            if ($cobro->rechazado()) {
                throw ValidationException::withMessages(['pago' => $cobro->motivo]);
            }

            $pedido = Pedido::create([
                'codigo' => $this->siguienteCodigo(),
                'user_id' => $usuario->id,
                ...$datosCliente,
                'subtotal' => $subtotal,
                'envio' => $envio,
                'total' => $total,
                'estado' => EstadoPedido::Pendiente,
                'metodo_pago' => $metodo,
                'estado_pago' => $cobro->estado,
                'referencia_pago' => $cobro->referencia,
                'pago_detalle' => $cobro->detalle,
                'pagado_at' => $cobro->aprobado() ? now() : null,
                'tipo_entrega' => $entrega,
            ]);

            foreach ($lineas as $linea) {
                $producto = $productos->get($linea['producto']->id);

                // Lanza si los lotes ya no cubren la cantidad; la transacción revierte.
                $producto->consumirDeLotes($linea['cantidad']);

                $pedido->lineas()->create([
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'presentacion' => $producto->presentacion,
                    'categoria' => $producto->categoria,
                    'precio' => $producto->precioVigente(),
                    'cantidad' => $linea['cantidad'],
                ]);
            }

            return $pedido;
        });

        $this->carrito->vaciar();

        return $pedido->load('lineas');
    }

    /**
     * Correlativo PED-### continuando desde el pedido más alto ya registrado.
     */
    private function siguienteCodigo(): string
    {
        $ultimo = Pedido::query()
            ->lockForUpdate()
            ->max(DB::raw('CAST(SUBSTRING(codigo, 5) AS UNSIGNED)'));

        return 'PED-'.(max((int) $ultimo, 100) + 1);
    }
}
