@props([
    // Sección del menú de la que se descarga el reporte.
    'seccion',
    // Filtros de la pantalla que deben viajar al reporte (periodo, búsqueda...).
    'parametros' => [],
])

@php
    $seccion = $seccion instanceof \App\Enums\Seccion ? $seccion : \App\Enums\Seccion::from($seccion);

    $consulta = collect($parametros)
        ->filter(fn ($valor) => $valor !== null && $valor !== '')
        ->all();

    $enlace = fn (\App\Enums\FormatoReporte $formato) => route('reportes.descargar', [
        ...$consulta,
        'seccion' => $seccion->value,
        'formato' => $formato->value,
    ]);
@endphp

<div {{ $attributes->class(['flex flex-wrap items-center gap-2']) }}>
    <span class="text-xs font-semibold uppercase tracking-wide text-coffee-700/50">Descargar</span>

    <a href="{{ $enlace(\App\Enums\FormatoReporte::Pdf) }}"
        class="inline-flex items-center gap-1.5 rounded-full bg-ladrillo-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 focus:outline-none focus-visible:ring-4 focus-visible:ring-ladrillo-500/30"
        title="Descargar el reporte de {{ $seccion->titulo() }} en PDF">
        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 2a1 1 0 011 1v7.6l2.3-2.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L9 10.6V3a1 1 0 011-1Z" />
            <path d="M3 14a1 1 0 011 1v1h12v-1a1 1 0 112 0v1.5A1.5 1.5 0 0116.5 18h-13A1.5 1.5 0 012 16.5V15a1 1 0 011-1Z" />
        </svg>
        PDF
    </a>

    <a href="{{ $enlace(\App\Enums\FormatoReporte::Excel) }}"
        class="inline-flex items-center gap-1.5 rounded-full bg-coffee-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-coffee-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30"
        title="Descargar el reporte de {{ $seccion->titulo() }} en Excel">
        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M4 2h7l5 5v11a1 1 0 01-1 1H4a1 1 0 01-1-1V3a1 1 0 011-1Zm7 1.5V7h3.5L11 3.5ZM6.6 9l1.6 2.4L6.5 14h1.6l.9-1.5.9 1.5h1.7l-1.7-2.6L11.6 9H10l-.9 1.4L8.2 9H6.6Z" />
        </svg>
        Excel
    </a>
</div>
