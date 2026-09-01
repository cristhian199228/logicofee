<?php

namespace App\Http\Controllers;

use App\Actions\RegistrarPedido;
use App\Http\Requests\StorePedidoRequest;
use App\Models\Pedido;
use App\Support\Carrito;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PedidoController extends Controller
{
    /**
     * Historial de pedidos. El cliente solo ve los suyos.
     */
    public function index(Request $request): View
    {
        $usuario = $request->user();

        $pedidos = Pedido::query()
            ->with('lineas')
            ->unless($usuario->rol->veTodosLosPedidos(), fn ($query) => $query->whereBelongsTo($usuario, 'usuario'))
            ->latest()
            ->latest('id')
            ->get();

        return view('pedidos.index', [
            'pedidos' => $pedidos,
        ]);
    }

    public function create(Request $request, Carrito $carrito): View
    {
        return view('pedidos.create', [
            'carrito' => $carrito,
            'usuario' => $request->user(),
        ]);
    }

    /**
     * Registra el pedido con estado inicial "Pendiente" (HU02).
     */
    public function store(StorePedidoRequest $request, RegistrarPedido $registrar): RedirectResponse
    {
        $pedido = $registrar->handle($request->validated(), $request->user());

        // El tablero de seguimiento destaca el último pedido de esta sesión.
        $request->session()->put('ultimo_pedido', $pedido->codigo);

        return redirect()
            ->route('catalogo.index')
            ->with('pedido_confirmado', [
                'codigo' => $pedido->codigo,
                'total' => (float) $pedido->total,
            ]);
    }
}
