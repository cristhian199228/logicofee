<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarritoRequest;
use App\Http\Requests\UpdateCarritoRequest;
use App\Models\Producto;
use App\Support\Carrito;
use Illuminate\Http\RedirectResponse;

class CarritoController extends Controller
{
    public function store(StoreCarritoRequest $request, Carrito $carrito): RedirectResponse
    {
        $producto = Producto::where('slug', $request->string('producto'))->firstOrFail();

        if ($carrito->disponible($producto) < 1) {
            return back()->with('aviso', "No quedan más unidades de {$producto->nombre}.");
        }

        $carrito->agregar($producto);

        return back();
    }

    public function update(UpdateCarritoRequest $request, Producto $producto, Carrito $carrito): RedirectResponse
    {
        $carrito->ajustar($producto, $request->integer('delta'));

        return back();
    }

    public function destroy(Producto $producto, Carrito $carrito): RedirectResponse
    {
        $carrito->quitar($producto);

        return back();
    }
}
