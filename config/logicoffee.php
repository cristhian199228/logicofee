<?php

use App\Enums\CategoriaProducto;
use App\Enums\Rol;

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

    /*
    |--------------------------------------------------------------------------
    | Cuentas de demostración
    |--------------------------------------------------------------------------
    |
    | Una cuenta por rol, con el menú y los permisos que el enum Rol define para
    | su área. Esta es la única definición y solo la usa el UsuarioSeeder: la
    | pantalla de ingreso no las muestra, para no publicar credenciales.
    |
    | Todas comparten la misma contraseña y deben cambiarse antes de un uso real.
    |
    */

    'password_demo' => env('LOGICOFFEE_PASSWORD_DEMO', 'demo1234'),

    'cuentas_demo' => [
        [
            'username' => 'admin',
            'name' => 'S. Machaca',
            'email' => 'admin@logicoffee.test',
            'rol' => Rol::Administrador->value,
            'iniciales' => 'SM',
            'descripcion' => 'Gestiona el funcionamiento del sistema y supervisa los pedidos.',
        ],
        [
            'username' => 'gerencia',
            'name' => 'R. Mamani',
            'email' => 'gerencia@logicoffee.test',
            'rol' => Rol::DireccionGeneral->value,
            'iniciales' => 'RM',
            'descripcion' => 'Revisa los indicadores de todas las áreas para tomar decisiones.',
        ],
        [
            'username' => 'marketing',
            'name' => 'L. Ticona',
            'email' => 'marketing@logicoffee.test',
            'rol' => Rol::MarketingVentas->value,
            'iniciales' => 'LT',
            'descripcion' => 'Arma las promociones del catálogo y sigue las ventas por cliente.',
        ],
        [
            'username' => 'logistica',
            'name' => 'J. Choque',
            'email' => 'logistica@logicoffee.test',
            'rol' => Rol::LogisticaAlmacen->value,
            'iniciales' => 'JC',
            'descripcion' => 'Controla el stock por lotes, las mermas y el despacho de los pedidos.',
        ],
        [
            'username' => 'produccion',
            'name' => 'M. Apaza',
            'email' => 'produccion@logicoffee.test',
            'rol' => Rol::ProduccionOperaciones->value,
            'iniciales' => 'MA',
            'descripcion' => 'Planifica el tueste y registra el control de calidad de cada lote.',
        ],
        [
            'username' => 'proveedor',
            'name' => 'C. Vargas',
            'email' => 'proveedor@logicoffee.test',
            'rol' => Rol::Proveedor->value,
            'iniciales' => 'CV',
            'descripcion' => 'Consulta los pedidos pendientes para preparar y coordinar la entrega del café.',
        ],
        [
            'username' => 'cliente',
            'name' => 'Cafetería Andina',
            'email' => 'cliente@logicoffee.test',
            'rol' => Rol::Cliente->value,
            'iniciales' => 'CA',
            'descripcion' => 'Explora el catálogo y registra pedidos de manera sencilla.',
        ],
    ],

];
