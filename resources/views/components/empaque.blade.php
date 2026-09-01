@props(['acento', 'nombre', 'clase' => 'h-28 w-auto'])

{{-- Ilustración del empaque que ocupa el lugar de la foto del producto. --}}
<svg viewBox="0 0 96 104" class="{{ $clase }}" role="img" aria-label="Empaque de {{ $nombre }}">
    <path d="M26 22h44l6 12v58a6 6 0 01-6 6H26a6 6 0 01-6-6V34l6-12Z" fill="{{ $acento }}"/>
    <path d="M26 22h44l6 12H20l6-12Z" fill="#17331c" opacity=".35"/>
    <rect x="33" y="48" width="30" height="32" rx="6" fill="#f4f8ee" opacity=".92"/>
    <path d="M38 56h15v9a7.5 7.5 0 01-15 0v-9Z" fill="{{ $acento }}"/>
    <path d="M53.5 58.5h2.5a3.5 3.5 0 010 7h-2.5" stroke="{{ $acento }}" stroke-width="2.5" fill="none"/>
    <rect x="36" y="75" width="19" height="2.5" rx="1.25" fill="{{ $acento }}"/>
    <path d="M40 40c0-3 4-3 4-6M48 40c0-3 4-3 4-6M56 40c0-3 4-3 4-6" stroke="#f4f8ee" stroke-width="2.5" stroke-linecap="round" fill="none" opacity=".7"/>
</svg>
