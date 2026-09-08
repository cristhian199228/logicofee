<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    /**
     * Cuentas de demostración, una por rol: administración, las cuatro áreas
     * empresariales, el proveedor y el cliente.
     */
    public function run(): void
    {
        $cuentas = [
            [
                'username' => 'admin',
                'name' => 'S. Machaca',
                'email' => 'admin@logicoffee.test',
                'rol' => Rol::Administrador,
                'iniciales' => 'SM',
                'descripcion' => 'Gestiona el funcionamiento del sistema y supervisa los pedidos.',
            ],
            [
                'username' => 'gerencia',
                'name' => 'R. Mamani',
                'email' => 'gerencia@logicoffee.test',
                'rol' => Rol::DireccionGeneral,
                'iniciales' => 'RM',
                'descripcion' => 'Revisa los indicadores de todas las áreas para tomar decisiones.',
            ],
            [
                'username' => 'marketing',
                'name' => 'L. Ticona',
                'email' => 'marketing@logicoffee.test',
                'rol' => Rol::MarketingVentas,
                'iniciales' => 'LT',
                'descripcion' => 'Arma las promociones del catálogo y sigue las ventas por cliente.',
            ],
            [
                'username' => 'logistica',
                'name' => 'J. Choque',
                'email' => 'logistica@logicoffee.test',
                'rol' => Rol::LogisticaAlmacen,
                'iniciales' => 'JC',
                'descripcion' => 'Controla el stock por lotes, las mermas y el despacho de los pedidos.',
            ],
            [
                'username' => 'produccion',
                'name' => 'M. Apaza',
                'email' => 'produccion@logicoffee.test',
                'rol' => Rol::ProduccionOperaciones,
                'iniciales' => 'MA',
                'descripcion' => 'Planifica el tueste y registra el control de calidad de cada lote.',
            ],
            [
                'username' => 'proveedor',
                'name' => 'C. Vargas',
                'email' => 'proveedor@logicoffee.test',
                'rol' => Rol::Proveedor,
                'iniciales' => 'CV',
                'descripcion' => 'Consulta los pedidos pendientes para preparar y coordinar la entrega del café.',
            ],
            [
                'username' => 'cliente',
                'name' => 'Cafetería Andina',
                'email' => 'cliente@logicoffee.test',
                'rol' => Rol::Cliente,
                'iniciales' => 'CA',
                'descripcion' => 'Explora el catálogo y registra pedidos de manera sencilla.',
            ],
        ];

        foreach ($cuentas as $cuenta) {
            User::updateOrCreate(
                ['username' => $cuenta['username']],
                [...$cuenta, 'password' => Hash::make('demo1234'), 'email_verified_at' => now(), 'activo' => true],
            );
        }
    }
}
