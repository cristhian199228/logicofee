<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBajaLoteRequest;
use App\Models\Lote;
use Illuminate\Http\RedirectResponse;

/**
 * Bajas por merma del almacén: unidades vencidas o dañadas que salen del
 * stock vendible sin pasar por un pedido.
 */
class BajaLoteController extends Controller
{
    public function store(StoreBajaLoteRequest $request, Lote $lote): RedirectResponse
    {
        $cantidad = $request->integer('cantidad');

        $lote->darDeBaja($cantidad, $request->string('motivo')->trim()->toString());

        return back()->with('aviso', "Se dieron de baja {$cantidad} uds del lote {$lote->codigo}: {$lote->baja_nota}.");
    }
}
