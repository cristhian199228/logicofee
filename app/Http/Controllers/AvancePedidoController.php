<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;

/**
 * Avance de un pedido a la siguiente etapa del flujo (HU03).
 */
class AvancePedidoController extends Controller
{
    public function store(Pedido $pedido): RedirectResponse
    {
        if (! $pedido->avanzar()) {
            return back()->with('aviso', "El pedido {$pedido->codigo} ya fue entregado.");
        }

        return back()->with('aviso', "{$pedido->codigo} pasó a {$pedido->estado->value}.");
    }
}
