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

    <header x-data="{ abierto: false }" @keydown.escape.window="abierto = false" @click.outside="abierto = false"
        class="sticky top-0 z-20 border-b border-coffee-200 bg-white">
        <div class="mx-auto max-w-6xl px-4">

            <div class="flex items-center gap-x-6 gap-y-3 py-3">

                <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2">
                    <span class="grid size-7 place-items-center rounded-full bg-coffee-700">
                        <svg class="size-4 text-coffee-200" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M4 8h11v5a4 4 0 01-4 4H8a4 4 0 01-4-4V8Zm12 0h1.5a2.5 2.5 0 010 5H16V8ZM4 19h13v2H4v-2Z" />
                        </svg>
                    </span>
                    <span class="font-display text-xl font-bold text-coffee-700">LogiCoffee</span>
                </a>

                <nav class="hidden min-w-0 flex-1 flex-wrap items-center gap-x-5 gap-y-2 text-sm md:flex" aria-label="Secciones">
                    @foreach ($usuario->rol->secciones() as $seccion)
                        <a href="{{ route($seccion->ruta()) }}" wire:navigate
                            @class([
                                'border-b-2 pb-1 transition',
                                'border-mostaza-500 font-semibold text-coffee-800' => request()->routeIs($seccion->ruta()),
                                'border-transparent text-coffee-700/70 hover:border-coffee-300 hover:text-coffee-800' => ! request()->routeIs($seccion->ruta()),
                            ])>{{ $seccion->titulo() }}</a>
                    @endforeach
                </nav>

                <div class="ml-auto flex shrink-0 items-center gap-3">
                    <p class="hidden leading-tight md:block">
                        <span class="block text-right font-semibold text-coffee-800">{{ $usuario->name }}</span>
                        <span class="block text-right text-xs text-coffee-700/60">({{ $usuario->rol->value }})</span>
                    </p>

                    <div class="hidden md:block">
                        <livewire:cerrar-sesion />
                    </div>

                    @if ($usuario->puedeVer(\App\Enums\Seccion::Pedido))
                        <livewire:carrito-contador />
                    @endif

                    <button type="button" @click="abierto = ! abierto" :aria-expanded="abierto ? 'true' : 'false'"
                        aria-controls="menu-movil" aria-label="Abrir menú"
                        class="grid size-11 place-items-center rounded-full border-2 border-coffee-300 text-coffee-700 transition hover:bg-coffee-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-700/25 md:hidden">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <g x-show="! abierto">
                                <path d="M4 7h16M4 12h16M4 17h16" />
                            </g>
                            <g x-show="abierto" x-cloak>
                                <path d="M6 6l12 12M18 6L6 18" />
                            </g>
                        </svg>
                    </button>
                </div>
            </div>

            <div id="menu-movil" x-show="abierto" x-cloak x-transition.origin.top
                class="border-t border-coffee-200 py-3 md:hidden">

                <nav class="flex flex-col" aria-label="Secciones">
                    @foreach ($usuario->rol->secciones() as $seccion)
                        <a href="{{ route($seccion->ruta()) }}" wire:navigate @click="abierto = false"
                            @class([
                                'rounded-lg border-l-4 px-3 py-2.5 text-sm transition',
                                'border-mostaza-500 bg-coffee-50 font-semibold text-coffee-800' => request()->routeIs($seccion->ruta()),
                                'border-transparent text-coffee-700/70 hover:bg-coffee-50 hover:text-coffee-800' => ! request()->routeIs($seccion->ruta()),
                            ])>{{ $seccion->titulo() }}</a>
                    @endforeach
                </nav>

                <div class="mt-3 flex items-center justify-between gap-3 border-t border-coffee-200 px-3 pt-3">
                    <p class="leading-tight">
                        <span class="block font-semibold text-coffee-800">{{ $usuario->name }}</span>
                        <span class="block text-xs text-coffee-700/60">({{ $usuario->rol->value }})</span>
                    </p>

                    <livewire:cerrar-sesion />
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-10">
        {{ $slot }}
    </main>

</body>
</html>
