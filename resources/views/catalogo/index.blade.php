<x-layouts.app titulo="Catálogo">

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

    <form method="GET" action="{{ route('catalogo.index') }}" class="mt-8">
        <input type="hidden" name="categoria" value="{{ $categoria?->value }}" />

        <label for="buscador" class="sr-only">Buscar café</label>
        <div class="relative">
            <svg class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-coffee-700/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" />
            </svg>
            <input type="search" id="buscador" name="q" value="{{ $busqueda }}"
                placeholder="Buscar café por nombre o descripción..."
                class="w-full rounded-full border border-coffee-300 bg-white py-3 pl-12 pr-4 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
        </div>
    </form>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        @foreach ([null, ...\App\Enums\CategoriaProducto::cases()] as $filtro)
            <a href="{{ route('catalogo.index', array_filter(['q' => $busqueda, 'categoria' => $filtro?->value])) }}"
                @class([
                    'rounded-full border px-4 py-1.5 text-sm font-semibold transition',
                    'border-coffee-700 bg-coffee-700 text-white' => $categoria === $filtro,
                    'border-coffee-300 bg-white text-coffee-700 hover:border-coffee-500' => $categoria !== $filtro,
                ])>{{ $filtro?->value ?? 'Todos' }}</a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($productos as $producto)
            <x-producto-tarjeta :$producto :$carrito :$puedeGestionarInventario />
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-coffee-300 p-10 text-center text-sm text-coffee-700/60">
                No encontramos cafés que coincidan con la búsqueda.
            </p>
        @endforelse
    </div>

    @if (! $carrito->vacio())
        <div class="mt-8">
            <div class="sticky bottom-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-coffee-700 px-6 py-4 text-white shadow-xl shadow-coffee-800/25">
                <p class="text-sm">
                    <span class="font-bold">{{ $carrito->unidades() }} {{ $carrito->unidades() === 1 ? 'unidad' : 'unidades' }}</span>
                    en tu pedido
                </p>
                <a href="{{ route('pedidos.create') }}"
                    class="rounded-full bg-mostaza-500 px-6 py-2 text-sm font-bold text-coffee-900 transition hover:bg-mostaza-400 focus:outline-none focus-visible:ring-4 focus-visible:ring-mostaza-400/40">
                    Continuar con el pedido →
                </a>
            </div>
        </div>
    @endif

</x-layouts.app>
