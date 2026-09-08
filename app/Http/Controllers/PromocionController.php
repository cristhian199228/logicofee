<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePromocionRequest;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Promociones del catálogo: qué productos se destacan, con qué descuento y
 * durante qué fechas (HU03).
 */
class PromocionController extends Controller
{
    public function index(): View
    {
        $productos = Producto::query()->orderBy('nombre')->get();

        return view('promociones.index', [
            'productos' => $productos,
            'vigentes' => $productos->filter->promocionVigente(),
            'programadas' => $productos->filter(
                fn (Producto $producto) => $producto->destacado && ! $producto->promocionVigente()
            ),
        ]);
    }

    /**
     * Marca o retira el producto de la sección de promociones del catálogo,
     * junto con la imagen del banner con la que se anuncia.
     */
    public function update(UpdatePromocionRequest $request, Producto $producto): RedirectResponse
    {
        $destacado = $request->boolean('destacado');

        $producto->update([
            'destacado' => $destacado,
            'promocion_titulo' => $destacado ? $request->string('promocion_titulo')->trim()->value() ?: null : null,
            'promocion_banner' => $this->banner($request, $producto),
            'descuento' => $destacado ? $request->integer('descuento') : 0,
            'promocion_inicia_at' => $destacado ? $request->date('promocion_inicia_at') : null,
            'promocion_termina_at' => $destacado ? $request->date('promocion_termina_at') : null,
        ]);

        return back()->with('aviso', $destacado
            ? "{$producto->nombre} se destaca en el catálogo con {$producto->descuento}% de descuento."
            : "{$producto->nombre} salió de las promociones.");
    }

    /**
     * Guarda la imagen nueva del banner, la retira si se pidió quitarla y
     * borra del disco la que quede sin uso.
     */
    private function banner(UpdatePromocionRequest $request, Producto $producto): ?string
    {
        $anterior = $producto->promocion_banner;

        $banner = match (true) {
            $request->hasFile('banner') => $request->file('banner')->store('promociones', 'public'),
            $request->boolean('quitar_banner') => null,
            default => $anterior,
        };

        if ($anterior !== null && $banner !== $anterior) {
            Storage::disk('public')->delete($anterior);
        }

        return $banner;
    }
}
