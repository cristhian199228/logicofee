<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaProducto;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Gestión del catálogo: alta, edición y baja de los cafés que se venden.
 * El stock no se edita aquí, sale de los lotes del almacén.
 */
class ProductoController extends Controller
{
    public function index(): View
    {
        $productos = Producto::query()
            ->withCount('lotes')
            ->orderBy('nombre')
            ->get();

        return view('productos.index', [
            'productos' => $productos,
            'destacados' => $productos->where('destacado', true)->count(),
            'bajoStock' => $productos->filter(fn (Producto $producto) => $producto->agotado() || $producto->bajoStock())->count(),
            'categorias' => CategoriaProducto::cases(),
        ]);
    }

    public function store(StoreProductoRequest $request): RedirectResponse
    {
        $producto = Producto::create([
            ...$request->safe()->except('foto'),
            'slug' => $this->slugUnico($request->string('nombre')->toString(), $request->string('presentacion')->toString()),
            'imagen' => $request->file('foto')?->store('productos', 'public'),
            'stock' => 0,
        ]);

        return back()->with('aviso', "{$producto->nombre} ({$producto->presentacion}) se agregó al catálogo. Registra un lote para darle stock.");
    }

    /**
     * Actualiza los datos del café. El slug no cambia: es la dirección con la
     * que el producto ya vive en el catálogo y en los carritos abiertos.
     */
    public function update(UpdateProductoRequest $request, Producto $producto): RedirectResponse
    {
        $anterior = $producto->imagen;

        $producto->update([
            ...$request->safe()->except('foto'),
            'imagen' => $request->hasFile('foto')
                ? $request->file('foto')->store('productos', 'public')
                : $anterior,
        ]);

        if ($anterior !== null && $producto->imagen !== $anterior) {
            Storage::disk('public')->delete($anterior);
        }

        return back()->with('aviso', "Se actualizó {$producto->nombre}.");
    }

    /**
     * Retira el café del catálogo. El historial de pedidos conserva su copia
     * de los datos, pero el producto no se borra si todavía tiene stock.
     */
    public function destroy(Producto $producto): RedirectResponse
    {
        if (! $producto->agotado()) {
            return back()->withErrors(
                ['eliminar' => "{$producto->nombre} todavía tiene {$producto->stock} uds en almacén. Da de baja sus lotes antes de retirarlo."],
                'producto-'.$producto->slug,
            );
        }

        foreach ([$producto->imagen, $producto->promocion_banner] as $archivo) {
            if ($archivo !== null) {
                Storage::disk('public')->delete($archivo);
            }
        }

        $nombre = $producto->nombre;
        $producto->delete();

        return back()->with('aviso', "{$nombre} salió del catálogo.");
    }

    /** Dirección del producto en el catálogo, sin chocar con otra ya usada. */
    private function slugUnico(string $nombre, string $presentacion): string
    {
        $base = Str::slug($nombre.' '.$presentacion);
        $slug = $base;
        $intento = 2;

        while (Producto::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$intento++;
        }

        return $slug;
    }
}
