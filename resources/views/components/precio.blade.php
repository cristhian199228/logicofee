@props(['valor'])

<span {{ $attributes }}>${{ number_format((float) $valor, 2) }}</span>
