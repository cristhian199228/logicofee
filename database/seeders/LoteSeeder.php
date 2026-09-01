<?php

namespace Database\Seeders;

use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LoteSeeder extends Seeder
{
    /**
     * Lotes de arranque. Las cantidades están calculadas para que, tras los
     * pedidos del PedidoSeeder, el almacén quede con stock realista: un
     * producto agotado, uno bajo mínimo y un lote próximo a vencer.
     */
    public function run(): void
    {
        $lotes = [
            // slug, código, cantidad, tostado (meses/días atrás), vence (meses/días adelante)
            ['bourbon-250', 'L-2601', 90, '-3 months', '+9 months'],
            ['bourbon-250', 'L-2612', 76, '-2 weeks', '+11 months'],
            ['geisha-1k', 'L-2604', 31, '-11 months', '+20 days'],
            ['mocha-500', 'L-2598', 6, '-5 months', '+7 months'],
            ['descafeinado-500', 'L-2603', 33, '-2 months', '+10 months'],
            ['descafeinado-500', 'L-2609', 30, '-3 days', '+12 months'],
            ['colombia-250', 'L-2605', 48, '-6 weeks', '+10 months'],
            ['colombia-250', 'L-2611', 30, '-1 week', '+11 months'],
        ];

        $productos = Producto::all()->keyBy('slug');

        foreach ($lotes as [$slug, $codigo, $cantidad, $tostado, $vence]) {
            $producto = $productos->get($slug);

            if ($producto === null || $producto->lotes()->where('codigo', $codigo)->exists()) {
                continue;
            }

            $producto->lotes()->create([
                'codigo' => $codigo,
                'cantidad_inicial' => $cantidad,
                'cantidad_disponible' => $cantidad,
                'tostado_at' => Carbon::parse($tostado),
                'vence_at' => Carbon::parse($vence),
            ]);
        }

        $productos->each->sincronizarStock();
    }
}
