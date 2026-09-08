<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAvancePedidoRequest;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;

/**
 * Avance de un pedido a la siguiente etapa del flujo (HU03). Al entregar se
 * anota quién recibió y, si el pago era en efectivo, se cierra el cobro.
 */
class AvancePedidoController extends Controller
{
    public function store(StoreAvancePedidoRequest $request, Pedido $pedido): RedirectResponse
    {
        $esEntrega = $request->esEntrega();
        $recibidoPor = $esEntrega ? $request->string('recibido_por')->trim()->value() ?: null : null;

        if (! $pedido->avanzar($recibidoPor)) {
            return back()->with('aviso', "El pedido {$pedido->codigo} ya fue entregado.");
        }

        $cobrado = $esEntrega && $request->boolean('cobrado') && $pedido->cobroPendiente();

        if ($cobrado) {
            $pedido->cobrar('COB-'.$pedido->codigo);
        }

        return back()->with('aviso', $cobrado
            ? "{$pedido->codigo} pasó a {$pedido->estado->value} y se cobró {$pedido->metodo_pago->value}."
            : "{$pedido->codigo} pasó a {$pedido->estado->value}.");
    }
}
