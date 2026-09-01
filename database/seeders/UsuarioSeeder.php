<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    /**
     * Cuentas de demostración, una por rol.
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
                [...$cuenta, 'password' => Hash::make('demo1234'), 'email_verified_at' => now()],
            );
        }
    }
}
