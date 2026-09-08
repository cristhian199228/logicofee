<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;

/**
 * Cierre manual del cobro de un pedido: el efectivo que entró al entregar o
 * la operación que el cliente rehízo después de un rechazo.
 */
class PagoPedidoController extends Controller
{
    public function update(Pedido $pedido): RedirectResponse
    {
        if ($pedido->pagado()) {
            return back()->with('aviso', "El pedido {$pedido->codigo} ya estaba cobrado.");
        }

        $pedido->cobrar('COB-'.$pedido->codigo);

        return back()->with('aviso', "Se registró el cobro de {$pedido->codigo} ({$pedido->metodo_pago->value}).");
    }
}
