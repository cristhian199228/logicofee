@props([
    'contenido',
    'etiqueta' => 'Código QR',
    'clase' => 'size-32',
])

@php($matriz = (new \App\Support\QrSimulado($contenido))->matriz())
@php($lienzo = \App\Support\QrSimulado::LADO + 4)

{{-- QR de demostración: dibuja la retícula, no codifica el contenido. --}}
<svg viewBox="-2 -2 {{ $lienzo }} {{ $lienzo }}" class="{{ $clase }}" shape-rendering="crispEdges"
    role="img" aria-label="{{ $etiqueta }}">
    <rect x="-2" y="-2" width="{{ $lienzo }}" height="{{ $lienzo }}" fill="#fff" />
    @foreach ($matriz as $fila => $modulos)
        @foreach ($modulos as $columna => $oscuro)
            @if ($oscuro)
                <rect x="{{ $columna }}" y="{{ $fila }}" width="1" height="1" fill="currentColor" />
            @endif
        @endforeach
    @endforeach
</svg>
