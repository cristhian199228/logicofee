@props([
    'etiqueta',
    'valor',
    'detalle' => null,
    // Tono de la tarjeta: neutro, aviso (mostaza) o alerta (ladrillo).
    'tono' => 'neutro',
])

<div @class([
    'rounded-2xl border p-5',
    'border-coffee-200 bg-white' => $tono === 'neutro',
    'border-mostaza-500 bg-mostaza-400/15' => $tono === 'aviso',
    'border-ladrillo-500/50 bg-ladrillo-500/5' => $tono === 'alerta',
])>
    <p class="text-xs font-semibold uppercase tracking-wide text-coffee-700/50">{{ $etiqueta }}</p>
    <p class="mt-1 font-display text-3xl font-bold text-coffee-800">{{ $valor }}</p>

    @if ($detalle)
        <p class="mt-1 text-xs text-coffee-700/60">{{ $detalle }}</p>
    @endif
</div>
