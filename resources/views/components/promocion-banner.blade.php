@props(['destacados'])

@php($conBanner = $destacados->filter->tieneBanner())

{{-- Sección de promociones del catálogo (HU03). --}}
<section class="mt-8 overflow-hidden rounded-3xl border-2 border-mostaza-500 bg-mostaza-400/15 p-6 sm:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-mostaza-500">Promociones vigentes</p>
            <h2 class="mt-1 font-display text-2xl font-bold text-coffee-800">Selección destacada de la semana</h2>
        </div>
        <span class="rounded-full bg-mostaza-500 px-4 py-1.5 text-sm font-bold text-coffee-900">
            {{ $destacados->count() }} {{ $destacados->count() === 1 ? 'producto' : 'productos' }}
        </span>
    </div>

    @if ($conBanner->isNotEmpty())
        <ul @class([
            'mt-5 grid gap-4',
            'sm:grid-cols-2' => $conBanner->count() > 1,
        ])>
            @foreach ($conBanner as $anunciado)
                <li class="relative overflow-hidden rounded-2xl border border-mostaza-500/40">
                    <img src="{{ $anunciado->urlBanner() }}"
                        alt="Banner de {{ $anunciado->promocion_titulo ?: $anunciado->nombre }}" loading="lazy"
                        class="h-48 w-full object-cover object-center sm:h-56" />

                    <div class="absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-coffee-900/85 via-coffee-900/35 to-transparent p-5">
                        @if ($anunciado->tieneDescuento())
                            <span class="self-start rounded-full bg-mostaza-500 px-3 py-1 text-xs font-bold text-coffee-900">
                                -{{ $anunciado->descuento }}% de descuento
                            </span>
                        @endif

                        <p class="mt-2 font-display text-2xl font-bold text-white">
                            {{ $anunciado->promocion_titulo ?: $anunciado->nombre }}
                        </p>
                        <p class="text-sm text-white/80">
                            {{ $anunciado->nombre }} · {{ $anunciado->presentacion }}
                        </p>

                        <p class="mt-1 flex items-baseline gap-2">
                            <x-precio :valor="$anunciado->precioVigente()" class="font-display text-xl font-bold text-white" />

                            @if ($anunciado->tieneDescuento())
                                <x-precio :valor="$anunciado->precio" class="text-sm text-white/60 line-through" />
                            @endif

                            @if ($anunciado->promocion_termina_at)
                                <span class="text-xs font-semibold text-white/70">
                                    hasta el {{ $anunciado->promocion_termina_at->format('d/m/Y') }}
                                </span>
                            @endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <ul class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($destacados as $destacado)
            <li class="flex items-center gap-3 rounded-2xl border border-mostaza-500/40 bg-white p-3">
                <x-producto-imagen :producto="$destacado" marco="size-16 shrink-0 rounded-xl" ilustracion="h-10 w-auto" />

                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-coffee-800">{{ $destacado->nombre }}</p>
                    <p class="truncate text-xs text-coffee-700/60">
                        {{ $destacado->promocion_titulo ?: $destacado->presentacion.' · '.$destacado->categoria->etiqueta() }}
                    </p>

                    <p class="mt-1 flex items-baseline gap-2">
                        <x-precio :valor="$destacado->precioVigente()" class="font-display text-lg font-bold text-coffee-800" />

                        @if ($destacado->tieneDescuento())
                            <x-precio :valor="$destacado->precio" class="text-xs text-coffee-700/50 line-through" />
                            <span class="rounded bg-mostaza-500 px-1.5 py-0.5 text-[11px] font-bold text-coffee-900">-{{ $destacado->descuento }}%</span>
                        @endif
                    </p>

                    @if ($destacado->promocion_termina_at)
                        <p class="mt-0.5 text-[11px] font-semibold text-coffee-700/50">
                            Válido hasta el {{ $destacado->promocion_termina_at->format('d/m/Y') }}
                        </p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
