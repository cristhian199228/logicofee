<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaProducto;
use App\Models\Producto;
use App\Support\Carrito;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogoController extends Controller
{
    /**
     * Catálogo con búsqueda por texto y filtro por categoría (HU01).
     */
    public function index(Request $request, Carrito $carrito): View
    {
        $categoria = CategoriaProducto::tryFrom($request->string('categoria')->toString());

        $productos = Producto::query()
            ->with(['lotes' => fn ($query) => $query->porVencimiento()])
            ->buscar($request->string('q')->toString())
            ->deCategoria($categoria)
            ->orderBy('nombre')
            ->get();

        return view('catalogo.index', [
            'productos' => $productos,
            'busqueda' => $request->string('q')->toString(),
            'categoria' => $categoria,
            'carrito' => $carrito,
            'puedeGestionarInventario' => $request->user()->rol->puedeGestionarInventario(),
        ]);
    }
}
