<?php

namespace Database\Seeders;

use App\Enums\EstadoPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PedidoSeeder extends Seeder
{
    /**
     * Pedidos de arranque: tres por cada cuenta de demostración, repartidos
     * entre las tres etapas del flujo. Cada uno consume sus lotes, así que el
     * stock del catálogo refleja lo que realmente salió del almacén.
     */
    public function run(): void
    {
        $pedidos = [
            // código, usuario que registra, cliente, tipo, teléfono, dirección, items, estado, horas atrás
            ['PED-095', 'cliente', 'Cafetería Andina', 'Cafetería', '945664313', 'Av. Ejército 401, Yanahuara',
                ['colombia-250' => 6, 'geisha-1k' => 2], EstadoPedido::Entregado, 76],
            ['PED-096', 'proveedor', 'Tostaduría Sur', 'Tienda especializada', '901336742', 'Calle Mercaderes 210, Cercado',
                ['bourbon-250' => 20], EstadoPedido::Entregado, 72],
            ['PED-097', 'admin', 'Café del Puerto', 'Cafetería', '934110265', 'Av. Dolores 155, José Luis Bustamante',
                ['descafeinado-500' => 8, 'mocha-500' => 4], EstadoPedido::Entregado, 54],
            ['PED-098', 'cliente', 'Cafetería Andina', 'Cafetería', '945664313', 'Av. Ejército 401, Yanahuara',
                ['bourbon-250' => 8, 'descafeinado-500' => 2], EstadoPedido::Preparacion, 30],
            ['PED-099', 'proveedor', 'Tienda El Grano', 'Tienda especializada', '958220147', 'Calle Jerusalén 502, Cercado',
                ['colombia-250' => 4, 'descafeinado-500' => 2], EstadoPedido::Preparacion, 27],
            ['PED-100', 'admin', 'Rest. Miraflores', 'Restaurante', '912447800', 'Av. Progreso 780, Miraflores',
                ['geisha-1k' => 22, 'bourbon-250' => 6], EstadoPedido::Preparacion, 25],
            ['PED-101', 'cliente', 'Cafetería Andina', 'Cafetería', '945664313', 'Av. Ejército 401, Yanahuara',
                ['bourbon-250' => 12, 'geisha-1k' => 3], EstadoPedido::Pendiente, 6],
            ['PED-102', 'proveedor', 'Hotel Sillar', 'Restaurante', '967408819', 'Av. Bolognesi 120, Yanahuara',
                ['colombia-250' => 5, 'mocha-500' => 2], EstadoPedido::Pendiente, 4],
            ['PED-103', 'admin', 'Bodega Central Arequipa', 'Consumidor final', '923118004', 'Calle San Juan de Dios 300, Cercado',
                ['descafeinado-500' => 6, 'colombia-250' => 3], EstadoPedido::Pendiente, 2],
        ];

        $productos = Producto::all()->keyBy('slug');
        $usuarios = User::all()->keyBy('username');
        $envio = (float) config('logicoffee.envio');

        foreach ($pedidos as [$codigo, $username, $nombre, $tipo, $telefono, $direccion, $items, $estado, $horasAtras]) {
            if (Pedido::where('codigo', $codigo)->exists()) {
                continue;
            }

            $registrado = Carbon::now()->subHours($horasAtras);
            $lineas = [];
            $subtotal = 0.0;

            foreach ($items as $slug => $cantidad) {
                $producto = $productos->get($slug);
                $producto->consumirDeLotes($cantidad);

                $subtotal += (float) $producto->precio * $cantidad;
                $lineas[] = [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'presentacion' => $producto->presentacion,
                    'categoria' => $producto->categoria,
                    'precio' => $producto->precio,
                    'cantidad' => $cantidad,
                ];
            }

            $pedido = new Pedido([
                'codigo' => $codigo,
                'user_id' => $usuarios->get($username)?->id,
                'cliente_nombre' => $nombre,
                'cliente_telefono' => $telefono,
                'cliente_correo' => null,
                'cliente_tipo' => $tipo,
                'cliente_direccion' => $direccion,
                'observaciones' => null,
                'subtotal' => $subtotal,
                'envio' => $envio,
                'total' => $subtotal + $envio,
                'estado' => $estado,
                'entregado_at' => $estado === EstadoPedido::Entregado
                    ? $registrado->copy()->addHours(2)
                    : null,
            ]);

            // Los pedidos de arranque conservan la hora en que se registraron.
            $pedido->created_at = $registrado;
            $pedido->updated_at = $registrado;
            $pedido->save();

            $pedido->lineas()->createMany($lineas);
        }
    }
}
