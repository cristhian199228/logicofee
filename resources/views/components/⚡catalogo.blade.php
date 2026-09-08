<?php

use App\Enums\CategoriaProducto;
use App\Models\Producto;
use App\Support\Carrito;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Catálogo con búsqueda por texto y filtro por categoría (HU01). Ambos filtran
 * mientras se escribe, sin recargar la página.
 */
new #[Layout('components.layouts.app', ['titulo' => 'Catálogo'])] class extends Component
{
    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    #[Url(except: '')]
    public string $categoria = '';

    public ?string $aviso = null;

    /** Pedido recién registrado que confirma el modal de bienvenida (HU02). */
    public ?array $pedidoConfirmado = null;

    public function mount(): void
    {
        $this->pedidoConfirmado = session('pedido_confirmado');
    }

    /**
     * @return Collection<int, Producto>
     */
    #[Computed]
    public function productos(): Collection
    {
        return Producto::query()
            ->with(['lotes' => fn ($query) => $query->porVencimiento()])
            ->buscar($this->busqueda)
            ->deCategoria(CategoriaProducto::tryFrom($this->categoria))
            ->orderBy('nombre')
            ->get();
    }

    /** Promociones vigentes del banner, sin depender de los filtros (HU03). */
    #[Computed]
    public function destacados(): Collection
    {
        return Producto::query()->enPromocion()->orderBy('nombre')->get();
    }

    #[Computed]
    public function carrito(): Carrito
    {
        return app(Carrito::class);
    }

    public function filtrarPor(?string $categoria): void
    {
        $this->categoria = $categoria ?? '';
    }

    #[On('carrito-actualizado')]
    public function refrescar(): void
    {
        unset($this->carrito, $this->productos);
    }

    #[On('aviso')]
    public function mostrarAviso(string $mensaje): void
    {
        $this->aviso = $mensaje;
    }
};
?>

<div>
    <x-aviso :mensaje="$aviso" />

    <section class="overflow-hidden rounded-3xl border border-coffee-200 bg-coffee-50">
        <div class="flex flex-wrap items-center gap-6 p-8 sm:p-10">
            <div class="min-w-64 flex-1">
                <h1 class="font-display text-3xl font-bold text-coffee-700">Granos Selectos de Origen Único</h1>
                <p class="mt-3 max-w-xl text-sm leading-relaxed text-coffee-700/70">
                    Nuestra plataforma conecta restaurantes exigentes y tiendas especializadas
                    con cosechas de café premium exclusivas de América Latina.
                </p>
            </div>
            <svg viewBox="0 0 120 120" class="hidden h-28 w-28 text-coffee-300 sm:block" aria-hidden="true">
                <path d="M96 24C60 24 34 44 30 78c-1 9 2 16 6 20 22 4 44-8 54-30 6-14 8-30 6-44Z" fill="currentColor" />
                <path d="M24 104c14-26 34-44 60-56" stroke="#2c5530" stroke-width="4" stroke-linecap="round" fill="none" />
            </svg>
        </div>
    </section>

    @if ($this->destacados->isNotEmpty())
        <x-promocion-banner :destacados="$this->destacados" />
    @endif

    <div class="mt-8">
        <label for="buscador" class="sr-only">Buscar café</label>
        <div class="relative">
            <svg class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-coffee-700/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" />
            </svg>
            <input type="search" id="buscador" wire:model.live.debounce.350ms="busqueda"
                placeholder="Buscar café por nombre o descripción..."
                class="w-full rounded-full border border-coffee-300 bg-white py-3 pl-12 pr-4 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        @foreach ([null, ...CategoriaProducto::cases()] as $filtro)
            <button type="button" wire:click="filtrarPor(@js($filtro?->value))"
                @class([
                    'rounded-full border px-4 py-1.5 text-sm font-semibold transition',
                    'border-coffee-700 bg-coffee-700 text-white' => $categoria === ($filtro?->value ?? ''),
                    'border-coffee-300 bg-white text-coffee-700 hover:border-coffee-500' => $categoria !== ($filtro?->value ?? ''),
                ])>{{ $filtro?->value ?? 'Todos' }}</button>
        @endforeach

        <x-reporte-descargas seccion="catalogo" :parametros="['q' => $busqueda, 'categoria' => $categoria]"
            class="ml-auto" />
    </div>

    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->productos as $producto)
            <livewire:producto-tarjeta :producto="$producto" :puede-editar-catalogo="auth()->user()->rol->puedeEditarCatalogo()"
                :key="'producto-'.$producto->id" />
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-coffee-300 p-10 text-center text-sm text-coffee-700/60">
                No encontramos cafés que coincidan con la búsqueda.
            </p>
        @endforelse
    </div>

    @if (! $this->carrito->vacio())
        <div class="mt-8">
            <div class="sticky bottom-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-coffee-700 px-6 py-4 text-white shadow-xl shadow-coffee-800/25">
                <p class="text-sm">
                    <span class="font-bold">{{ $this->carrito->unidades() }} {{ $this->carrito->unidades() === 1 ? 'unidad' : 'unidades' }}</span>
                    en tu pedido
                </p>
                <a href="{{ route('pedidos.create') }}" wire:navigate
                    class="rounded-full bg-mostaza-500 px-6 py-2 text-sm font-bold text-coffee-900 transition hover:bg-mostaza-400 focus:outline-none focus-visible:ring-4 focus-visible:ring-mostaza-500/40">
                    Continuar con el pedido →
                </a>
            </div>
        </div>
    @endif

    @if ($pedidoConfirmado)
        <dialog x-data x-init="$el.showModal()"
            class="w-full max-w-sm rounded-3xl border-2 border-coffee-300 bg-coffee-50 p-8 text-center shadow-2xl backdrop:bg-coffee-900/50"
            aria-labelledby="modal-titulo">
            <div class="mx-auto grid size-14 place-items-center rounded-full bg-coffee-200">
                <svg class="size-7 text-coffee-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12.5l4.5 4.5L19 7.5" />
                </svg>
            </div>

            <h2 id="modal-titulo" class="mt-5 font-display text-2xl font-bold text-coffee-700">¡Pedido Confirmado!</h2>
            <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-coffee-700/70">
                El pedido se ha registrado con estado inicial
                <strong class="font-bold text-coffee-800">"Pendiente"</strong>.
            </p>

            <dl class="mt-6 space-y-2 rounded-xl border border-coffee-300 bg-white px-4 py-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-coffee-700/60">Identificador:</dt>
                    <dd class="font-bold text-coffee-800">{{ $pedidoConfirmado['codigo'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-coffee-700/60">Total Registrado:</dt>
                    <x-precio :valor="$pedidoConfirmado['total']" class="font-bold text-coffee-800" />
                </div>
            </dl>

            <a href="{{ route('pedidos.index') }}" wire:navigate
                class="mt-6 block w-full rounded-xl bg-coffee-500 py-3 font-semibold text-white transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30">
                Ver Historial de Pedidos
            </a>

            <form method="dialog">
                <button type="submit"
                    class="mt-2 w-full rounded-xl py-2 text-sm font-semibold text-coffee-700/70 transition hover:text-coffee-800">
                    Seguir en el catálogo
                </button>
            </form>
        </dialog>
    @endif
</div>
