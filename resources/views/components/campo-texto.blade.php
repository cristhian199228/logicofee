@props([
    'nombre',
    'etiqueta',
    'valor' => '',
    'marcador' => '',
    'tipo' => 'text',
    'requerido' => false,
])

<div>
    <label for="{{ $nombre }}" class="block text-sm font-semibold text-coffee-800">
        {{ $etiqueta }}
        @if ($requerido)
            <span class="text-ladrillo-500" aria-hidden="true">*</span>
        @endif
    </label>

    <input type="{{ $tipo }}" id="{{ $nombre }}" name="{{ $nombre }}" value="{{ old($nombre, $valor) }}"
        placeholder="{{ $marcador }}"
        @if ($requerido) required @endif
        {{ $attributes->merge(['class' => 'mt-1.5 w-full rounded-xl border bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:outline-none focus:ring-4 '.($errors->has($nombre) ? 'border-ladrillo-500 focus:border-ladrillo-500 focus:ring-ladrillo-500/15' : 'border-coffee-300 focus:border-coffee-500 focus:ring-coffee-500/15')]) }} />

    @error($nombre)
        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
    @enderror
</div>
