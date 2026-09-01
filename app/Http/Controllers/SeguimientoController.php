<?php

namespace App\Http\Controllers;

use App\Enums\EstadoPedido;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeguimientoController extends Controller
{
    /**
     * Tablero Pendiente → Preparación → Entregado (HU03). El cliente lo ve en
     * modo consulta y solo con sus propios pedidos.
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

        return view('seguimiento.index', [
            'columnas' => collect(EstadoPedido::cases())
                ->mapWithKeys(fn (EstadoPedido $estado) => [
                    $estado->value => $pedidos->where('estado', $estado),
                ]),
            'total' => $pedidos->count(),
            'recienRegistrado' => session('ultimo_pedido'),
            'puedeAvanzar' => $usuario->rol->puedeGestionarInventario(),
        ]);
    }
}
