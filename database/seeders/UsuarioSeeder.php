<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    /**
     * Cuentas de demostración, una por rol: administración, las cuatro áreas
     * empresariales, el proveedor y el cliente.
     *
     * Las cuentas se definen en config/logicoffee.php, que es también lo que
     * lista la pantalla de ingreso: así ambas no pueden desincronizarse.
     */
    public function run(): void
    {
        $password = Hash::make(config('logicoffee.password_demo'));

        foreach (config('logicoffee.cuentas_demo') as $cuenta) {
            User::updateOrCreate(
                ['username' => $cuenta['username']],
                [...$cuenta, 'password' => $password, 'email_verified_at' => now(), 'activo' => true],
            );
        }
    }
}
