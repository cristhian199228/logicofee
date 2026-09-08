<?php

namespace Database\Seeders;

use App\Enums\ResultadoCalidad;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LoteSeeder extends Seeder
{
    /**
     * Lotes de arranque. Las cantidades están calculadas para que, tras los
     * pedidos del PedidoSeeder, el almacén quede con stock realista: un
     * producto agotado, uno bajo mínimo y un lote próximo a vencer.
     *
     * El control de calidad arranca con lotes aprobados, uno sin evaluar y uno
     * rechazado que queda bloqueado para la venta (HU07).
     */
    public function run(): void
    {
        $lotes = [
            // slug, código, cantidad, tostado (meses/días atrás), vence (meses/días adelante), calidad
            ['bourbon-250', 'L-2601', 90, '-3 months', '+9 months', ResultadoCalidad::Aprobado],
            ['bourbon-250', 'L-2612', 76, '-2 weeks', '+11 months', ResultadoCalidad::Aprobado],
            ['geisha-1k', 'L-2604', 31, '-11 months', '+20 days', ResultadoCalidad::Aprobado],
            ['mocha-500', 'L-2598', 6, '-5 months', '+7 months', ResultadoCalidad::Aprobado],
            ['mocha-500', 'L-2613', 24, '-1 month', '+11 months', ResultadoCalidad::Rechazado],
            ['descafeinado-500', 'L-2603', 33, '-2 months', '+10 months', ResultadoCalidad::Aprobado],
            ['descafeinado-500', 'L-2609', 30, '-3 days', '+12 months', ResultadoCalidad::Pendiente],
            ['colombia-250', 'L-2605', 48, '-6 weeks', '+10 months', ResultadoCalidad::Aprobado],
            ['colombia-250', 'L-2611', 30, '-1 week', '+11 months', ResultadoCalidad::Aprobado],
        ];

        $productos = Producto::all()->keyBy('slug');

        $responsable = User::where('username', 'proveedor')->first();

        foreach ($lotes as [$slug, $codigo, $cantidad, $tostado, $vence, $calidad]) {
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
                'calidad' => $calidad,
                'calidad_nota' => $calidad === ResultadoCalidad::Rechazado
                    ? 'Humedad por encima del rango permitido.'
                    : null,
                'evaluado_at' => $calidad->evaluable() ? Carbon::parse($tostado)->addDays(2) : null,
                'evaluado_por' => $calidad->evaluable() ? $responsable?->id : null,
            ]);
        }

        $productos->each->sincronizarStock();
    }
}
