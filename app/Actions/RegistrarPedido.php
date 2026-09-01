<?php

namespace App\Actions;

use App\Enums\EstadoPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Support\Carrito;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra el pedido con estado inicial "Pendiente" y consume los lotes (HU02).
 */
class RegistrarPedido
{
    public function __construct(private Carrito $carrito) {}

    /**
     * @param  array{cliente_nombre: string, cliente_telefono: string, cliente_correo?: ?string, cliente_tipo: string, cliente_direccion?: ?string, observaciones?: ?string}  $datosCliente
     *
     * @throws ValidationException si otro pedido agotó el stock mientras tanto.
     */
    public function handle(array $datosCliente, User $usuario): Pedido
    {
        $lineas = $this->carrito->lineas();

        if ($lineas->isEmpty()) {
            throw ValidationException::withMessages([
                'carrito' => 'Agrega al menos un producto antes de registrar el pedido.',
            ]);
        }

        $pedido = DB::transaction(function () use ($lineas, $datosCliente, $usuario): Pedido {
            $productos = Producto::whereKey($lineas->pluck('producto.id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = $lineas->sum(
                fn (array $linea) => (float) $productos->get($linea['producto']->id)->precio * $linea['cantidad']
            );

            $envio = (float) config('logicoffee.envio');

            $pedido = Pedido::create([
                'codigo' => $this->siguienteCodigo(),
                'user_id' => $usuario->id,
                ...$datosCliente,
                'subtotal' => $subtotal,
                'envio' => $envio,
                'total' => $subtotal + $envio,
                'estado' => EstadoPedido::Pendiente,
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
                    'precio' => $producto->precio,
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
