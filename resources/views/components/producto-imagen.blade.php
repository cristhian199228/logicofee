@props([
    'producto',
    // El marco lo define quien la usa: tarjeta ancha del catálogo o miniatura.
    'marco' => 'h-56 w-full rounded-t-2xl',
    'ilustracion' => 'h-32 w-auto',
])

@if ($producto->tieneFoto())
    <img src="{{ $producto->urlFoto() }}" alt="Foto de {{ $producto->nombre }}" loading="lazy"
        class="{{ $marco }} object-cover object-center" />
@else
    <div class="{{ $marco }} flex items-center justify-center bg-coffee-100">
        <x-empaque :acento="$producto->acento" :nombre="$producto->nombre" :clase="$ilustracion" />
    </div>
@endif
