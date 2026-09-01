<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $titulo ?? 'LogiCoffee' }} · LogiCoffee</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-coffee-100 font-sans antialiased">

    @php($usuario = auth()->user())

    <header class="sticky top-0 z-10 border-b border-coffee-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-8 gap-y-3 px-4 py-3">

            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2">
                <span class="grid size-7 place-items-center rounded-full bg-coffee-700">
                    <svg class="size-4 text-coffee-200" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M4 8h11v5a4 4 0 01-4 4H8a4 4 0 01-4-4V8Zm12 0h1.5a2.5 2.5 0 010 5H16V8ZM4 19h13v2H4v-2Z" />
                    </svg>
                </span>
                <span class="font-display text-xl font-bold text-coffee-700">LogiCoffee</span>
            </a>

            <nav class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm" aria-label="Secciones">
                @foreach ($usuario->rol->secciones() as $seccion)
                    <a href="{{ route($seccion->ruta()) }}"
                        @class([
                            'border-b-2 pb-1 transition',
                            'border-mostaza-500 font-semibold text-coffee-800' => request()->routeIs($seccion->ruta()),
                            'border-transparent text-coffee-700/70 hover:border-coffee-300 hover:text-coffee-800' => ! request()->routeIs($seccion->ruta()),
                        ])>{{ $seccion->titulo() }}</a>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-3">
                <p class="hidden leading-tight sm:block">
                    <span class="block text-right font-semibold text-coffee-800">{{ $usuario->name }}</span>
                    <span class="block text-right text-xs text-coffee-700/60">({{ $usuario->rol->value }})</span>
                </p>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="rounded-full border-2 border-ladrillo-500 px-4 py-1.5 text-sm font-semibold text-ladrillo-500 transition hover:bg-ladrillo-500 hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-ladrillo-500/25">
                        Cerrar sesión
                    </button>
                </form>

                @if ($usuario->puedeVer(\App\Enums\Seccion::Pedido))
                    <a href="{{ route('pedidos.create') }}" aria-label="Ver pedido"
                        class="relative grid size-11 place-items-center rounded-full bg-coffee-700 text-white transition hover:bg-coffee-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-700/30">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 4h2l2.4 11.2a2 2 0 002 1.6h7.7a2 2 0 002-1.6L20.5 7H6" />
                            <circle cx="10" cy="20" r="1.4" fill="currentColor" />
                            <circle cx="17" cy="20" r="1.4" fill="currentColor" />
                        </svg>

                        @if ($unidades = app(\App\Support\Carrito::class)->unidades())
                            <span class="absolute -right-1 -top-1 grid size-5 place-items-center rounded-full bg-mostaza-500 text-[11px] font-bold text-coffee-900">{{ $unidades }}</span>
                        @endif
                    </a>
                @endif
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-10">
        @if (session('aviso'))
            <p class="mb-6 rounded-xl border border-coffee-300 bg-coffee-50 px-4 py-3 text-sm font-medium text-coffee-800" role="status">
                {{ session('aviso') }}
            </p>
        @endif

        {{ $slot }}
    </main>

    @if ($confirmado = session('pedido_confirmado'))
        <dialog id="modal-confirmacion"
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
                    <dd class="font-bold text-coffee-800">{{ $confirmado['codigo'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-coffee-700/60">Total Registrado:</dt>
                    <x-precio :valor="$confirmado['total']" class="font-bold text-coffee-800" />
                </div>
            </dl>

            <a href="{{ route('pedidos.index') }}"
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

</body>
</html>
