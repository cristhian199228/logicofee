<?php

use App\Enums\CategoriaProducto;

return [

    /*
    |--------------------------------------------------------------------------
    | Costo de envío
    |--------------------------------------------------------------------------
    |
    | Cargo fijo que se suma al subtotal de todo pedido con al menos un
    | producto. Sale del wireframe de Registro de Pedidos del Sprint 1.
    |
    */

    'envio' => (float) env('LOGICOFFEE_ENVIO', 8.00),

    /*
    |--------------------------------------------------------------------------
    | Tipos de cliente
    |--------------------------------------------------------------------------
    |
    | Opciones del formulario de registro de pedidos.
    |
    */

    'tipos_cliente' => [
        'Restaurante',
        'Cafetería',
        'Tienda especializada',
        'Consumidor final',
    ],

    /*
    |--------------------------------------------------------------------------
    | Categoría por defecto
    |--------------------------------------------------------------------------
    */

    'categoria_por_defecto' => CategoriaProducto::EnGrano->value,

];
