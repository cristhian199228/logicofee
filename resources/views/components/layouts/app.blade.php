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

            <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2">
                <span class="grid size-7 place-items-center rounded-full bg-coffee-700">
                    <svg class="size-4 text-coffee-200" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M4 8h11v5a4 4 0 01-4 4H8a4 4 0 01-4-4V8Zm12 0h1.5a2.5 2.5 0 010 5H16V8ZM4 19h13v2H4v-2Z" />
                    </svg>
                </span>
                <span class="font-display text-xl font-bold text-coffee-700">LogiCoffee</span>
            </a>

            <nav class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm" aria-label="Secciones">
                @foreach ($usuario->rol->secciones() as $seccion)
                    <a href="{{ route($seccion->ruta()) }}" wire:navigate
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

                <livewire:cerrar-sesion />

                @if ($usuario->puedeVer(\App\Enums\Seccion::Pedido))
                    <livewire:carrito-contador />
                @endif
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-10">
        {{ $slot }}
    </main>

</body>
</html>
