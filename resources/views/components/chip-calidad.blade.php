@props(['calidad'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide '.$calidad->clasesChip()]) }}>
    <span class="size-1.5 rounded-full bg-current"></span>{{ $calidad->value }}
</span>
