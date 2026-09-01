<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProductoFotoRequest;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Foto del producto en el catálogo.
 */
class ProductoFotoController extends Controller
{
    public function update(UpdateProductoFotoRequest $request, Producto $producto): RedirectResponse
    {
        $anterior = $producto->imagen;

        $producto->update([
            'imagen' => $request->file('foto')->store('productos', 'public'),
        ]);

        if ($anterior !== null) {
            Storage::disk('public')->delete($anterior);
        }

        return back()->with('aviso', "Se actualizó la foto de {$producto->nombre}.");
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        if ($producto->imagen !== null) {
            Storage::disk('public')->delete($producto->imagen);
            $producto->update(['imagen' => null]);
        }

        return back()->with('aviso', "Se quitó la foto de {$producto->nombre}.");
    }
}
