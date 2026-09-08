@props(['mensaje' => null])

{{-- Mensaje de resultado de una acción. En las pantallas Livewire lo pinta el
     propio componente, para que se refresque sin recargar la página. --}}
@if ($mensaje)
    <p class="mb-6 rounded-xl border border-coffee-300 bg-coffee-50 px-4 py-3 text-sm font-medium text-coffee-800" role="status">
        {{ $mensaje }}
    </p>
@endif
