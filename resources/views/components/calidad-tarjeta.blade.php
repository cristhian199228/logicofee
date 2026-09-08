@props(['lote'])

@php($producto = $lote->producto)

<article @class([
    'rounded-2xl border bg-white p-5',
    'border-ladrillo-500/50 bg-ladrillo-500/5' => $lote->bloqueado(),
    'border-coffee-200' => ! $lote->bloqueado(),
])>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <x-producto-imagen :$producto marco="size-14 shrink-0 rounded-xl" ilustracion="h-9 w-auto" />
            <div class="min-w-0">
                <p class="font-mono text-sm font-bold text-coffee-800">{{ $lote->codigo }}</p>
                <p class="text-sm font-semibold text-coffee-800">{{ $producto->nombre }}</p>
                <p class="text-xs text-coffee-700/60">
                    {{ $producto->presentacion }} · tostado {{ $lote->tostado_at->format('d/m/Y') }} ·
                    vence {{ $lote->vence_at->format('d/m/Y') }}
                </p>
            </div>
        </div>

        <div class="text-right">
            <x-chip-calidad :calidad="$lote->calidad" />
            <p class="mt-1 text-sm font-semibold text-coffee-800">
                {{ $lote->cantidad_disponible }} / {{ $lote->cantidad_inicial }} uds
            </p>
        </div>
    </div>

    @if ($lote->evaluado())
        <p class="mt-4 rounded-xl border border-coffee-200 bg-coffee-50 px-4 py-3 text-xs text-coffee-700/80">
            <span class="font-semibold text-coffee-800">{{ $lote->calidad->descripcion() }}</span>
            · {{ $lote->evaluado_at?->translatedFormat('d/m/Y, H:i') }}
            @if ($lote->evaluador)
                · registrado por {{ $lote->evaluador->name }}
            @endif
            @if ($lote->calidad_nota)
                <span class="mt-1 block text-coffee-700/70">“{{ $lote->calidad_nota }}”</span>
            @endif
        </p>
    @endif

    <form method="POST" action="{{ route('calidad.store', $lote) }}"
        class="mt-4 flex flex-wrap items-end gap-3 border-t border-coffee-200 pt-4">
        @csrf

        <div class="min-w-56 flex-1">
            <label for="nota-{{ $lote->id }}" class="block text-sm font-semibold text-coffee-800">
                {{ $lote->evaluado() ? 'Nueva observación' : 'Observación del control' }}
            </label>
            <input type="text" id="nota-{{ $lote->id }}" name="calidad_nota" maxlength="255"
                value="{{ $errors->getBag('calidad-'.$lote->id)->any() ? old('calidad_nota') : '' }}"
                placeholder="Ej. Taza limpia, humedad 11%"
                class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
            @error('calidad_nota', 'calidad-'.$lote->id)
                <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" name="resultado" value="{{ \App\Enums\ResultadoCalidad::Aprobado->value }}"
                class="rounded-xl bg-coffee-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30">
                Aprobar lote
            </button>

            <button type="submit" name="resultado" value="{{ \App\Enums\ResultadoCalidad::Rechazado->value }}"
                class="rounded-xl border-2 border-ladrillo-500 px-5 py-2.5 text-sm font-bold text-ladrillo-500 transition hover:bg-ladrillo-500 hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-ladrillo-500/25">
                Rechazar lote
            </button>
        </div>
    </form>
</article>
