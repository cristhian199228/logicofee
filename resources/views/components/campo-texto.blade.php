@props([
    'campo',
    'etiqueta',
    'marcador' => '',
    'tipo' => 'text',
    'requerido' => false,
])

{{-- Campo de texto enlazado a una propiedad del componente Livewire que lo usa. --}}
<div>
    <label for="{{ $campo }}" class="block text-sm font-semibold text-coffee-800">
        {{ $etiqueta }}
        @if ($requerido)
            <span class="text-ladrillo-500" aria-hidden="true">*</span>
        @endif
    </label>

    <input type="{{ $tipo }}" id="{{ $campo }}" wire:model="{{ $campo }}"
        placeholder="{{ $marcador }}"
        {{ $attributes->merge(['class' => 'mt-1.5 w-full rounded-xl border bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:outline-none focus:ring-4 '.($errors->has($campo) ? 'border-ladrillo-500 focus:border-ladrillo-500 focus:ring-ladrillo-500/15' : 'border-coffee-300 focus:border-coffee-500 focus:ring-coffee-500/15')]) }} />

    @error($campo)
        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
    @enderror
</div>
