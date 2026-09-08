<?php

use App\Enums\Seccion;
use App\Models\Producto;
use App\Support\Carrito;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Tarjeta del catálogo: agrega el producto al pedido en construcción y, para
 * quien edita el catálogo, cambia la foto sin salir de la pantalla.
 */
new class extends Component
{
    use WithFileUploads;

    public Producto $producto;

    public bool $puedeEditarCatalogo = false;

    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp|max:4096', as: 'foto del producto')]
    public $foto = null;

    public function agregar(Carrito $carrito): void
    {
        abort_unless(auth()->user()->puedeVer(Seccion::Pedido), 403);

        if ($carrito->disponible($this->producto) < 1) {
            $this->dispatch('aviso', mensaje: "No quedan más unidades de {$this->producto->nombre}.");

            return;
        }

        $carrito->agregar($this->producto);

        $this->dispatch('carrito-actualizado');
    }

    public function subirFoto(): void
    {
        $this->authorize('editar-catalogo');

        $this->validate(['foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']], attributes: [
            'foto' => 'foto del producto',
        ]);

        $anterior = $this->producto->imagen;

        $this->producto->update(['imagen' => $this->foto->store('productos', 'public')]);

        if ($anterior !== null) {
            Storage::disk('public')->delete($anterior);
        }

        $this->reset('foto');

        $this->dispatch('aviso', mensaje: "Se actualizó la foto de {$this->producto->nombre}.");
    }

    public function quitarFoto(): void
    {
        $this->authorize('editar-catalogo');

        if ($this->producto->imagen !== null) {
            Storage::disk('public')->delete($this->producto->imagen);
            $this->producto->update(['imagen' => null]);
        }

        $this->dispatch('aviso', mensaje: "Se quitó la foto de {$this->producto->nombre}.");
    }
};
?>

@php
    $carrito = app(Carrito::class);
    $disponible = $carrito->disponible($producto);
    $enPedido = $carrito->cantidadDe($producto);
    $lote = $producto->loteActivo();
@endphp

<article @class([
    'relative flex flex-col rounded-2xl border border-coffee-200 bg-white transition hover:border-coffee-300 hover:shadow-lg hover:shadow-coffee-800/5',
    'opacity-60' => $producto->agotado(),
])>
    @if ($producto->promocionVigente())
        <span class="absolute left-4 top-4 z-10 rounded-full bg-mostaza-500 px-2.5 py-1 text-xs font-bold text-coffee-900">
            {{ $producto->tieneDescuento() ? '-'.$producto->descuento.'%' : 'Destacado' }}
        </span>
    @endif

    @if ($enPedido > 0)
        <span class="absolute right-4 top-4 z-10 rounded-full bg-coffee-700 px-2.5 py-1 text-xs font-bold text-white">
            {{ $enPedido }} en el pedido
        </span>
    @endif

    <x-producto-imagen :$producto />

    <div class="flex flex-1 flex-col p-5">
        <span class="self-start rounded-full border border-coffee-300 px-2.5 py-0.5 text-xs font-semibold text-coffee-700">
            {{ $producto->categoria->etiqueta() }}
        </span>

        <h3 class="mt-3 font-display text-lg font-bold text-coffee-800">{{ $producto->nombre }}</h3>
        <p class="text-xs font-medium text-coffee-700/50">
            {{ $producto->presentacion }}
            @if ($lote)
                · Lote {{ $lote->codigo }} · vence {{ $lote->vence_at->format('m/Y') }}
            @endif
        </p>
        <p class="mt-2 flex-1 text-sm leading-relaxed text-coffee-700/70">{{ $producto->descripcion }}</p>

        @if ($producto->promocionVigente() && $producto->promocion_titulo)
            <p class="mt-2 rounded-lg bg-mostaza-400/20 px-3 py-1.5 text-xs font-semibold text-coffee-800">
                {{ $producto->promocion_titulo }}
                @if ($producto->promocion_termina_at)
                    · hasta el {{ $producto->promocion_termina_at->format('d/m') }}
                @endif
            </p>
        @endif

        @if ($producto->bajoStock())
            <p class="mt-1 text-xs font-semibold text-mostaza-500">Quedan {{ $producto->stock }} uds en almacén</p>
        @endif

        <div class="mt-4 flex items-end justify-between gap-3">
            <p>
                <span class="block text-xs font-semibold uppercase tracking-wide text-coffee-700/50">Precio</span>
                <x-precio :valor="$producto->precioVigente()" class="font-display text-2xl font-bold text-coffee-800" />

                @if ($producto->tieneDescuento())
                    <span class="ml-1 text-sm text-coffee-700/50">
                        antes <x-precio :valor="$producto->precio" class="line-through" />
                    </span>
                @endif
            </p>

            @if ($producto->agotado())
                <span class="rounded-full bg-coffee-100 px-5 py-2 text-sm font-semibold text-coffee-700/40">Sin stock</span>
            @elseif ($disponible === 0)
                <span class="rounded-full bg-coffee-100 px-5 py-2 text-sm font-semibold text-coffee-700/40">Sin más unidades</span>
            @else
                <button type="button" wire:click="agregar" wire:loading.attr="disabled" wire:target="agregar"
                    class="rounded-full bg-mostaza-500 px-5 py-2 text-sm font-bold text-coffee-900 transition hover:bg-mostaza-400 focus:outline-none focus-visible:ring-4 focus-visible:ring-mostaza-500/30 active:scale-95 disabled:opacity-70">
                    + Agregar
                </button>
            @endif
        </div>

        @if ($puedeEditarCatalogo)
            <div class="mt-4 border-t border-coffee-200 pt-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-coffee-700/40">Foto del producto</p>

                <div class="mt-2 flex items-center gap-2">
                    <form wire:submit="subirFoto" class="flex min-w-0 flex-1 items-center gap-2">
                        <label for="foto-{{ $producto->slug }}" class="sr-only">Foto de {{ $producto->nombre }}</label>
                        <input type="file" id="foto-{{ $producto->slug }}" wire:model="foto" accept="image/*"
                            class="min-w-0 flex-1 text-xs text-coffee-700/60 file:mr-2 file:cursor-pointer file:rounded-full file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-700 hover:file:bg-coffee-200" />
                        <button type="submit"
                            class="shrink-0 rounded-full border border-coffee-300 px-3 py-1.5 text-xs font-semibold text-coffee-700 transition hover:border-coffee-500">
                            Subir
                        </button>
                    </form>

                    @if ($producto->tieneFoto())
                        <button type="button" wire:click="quitarFoto"
                            class="shrink-0 rounded-full border border-ladrillo-500/40 px-3 py-1.5 text-xs font-semibold text-ladrillo-500 transition hover:bg-ladrillo-500/10">
                            Quitar
                        </button>
                    @endif
                </div>

                @error('foto')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </div>
</article>
