<x-layouts.app titulo="Control de calidad">

    @php($resultados = \App\Enums\ResultadoCalidad::class)

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Control de calidad de lotes</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Registra el resultado del control de cada lote. Un lote rechazado queda bloqueado
                para la venta y deja de sumar al stock del catálogo.
            </p>
        </div>
        <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Producción</span>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-indicador etiqueta="Por controlar" :valor="$conteos[$resultados::Pendiente->value]"
            detalle="Lotes sin resultado registrado"
            :tono="$conteos[$resultados::Pendiente->value] > 0 ? 'aviso' : 'neutro'" />
        <x-indicador etiqueta="Aprobados" :valor="$conteos[$resultados::Aprobado->value]" detalle="Aptos para la venta" />
        <x-indicador etiqueta="Rechazados" :valor="$conteos[$resultados::Rechazado->value]"
            detalle="Bloqueados para la venta"
            :tono="$conteos[$resultados::Rechazado->value] > 0 ? 'alerta' : 'neutro'" />
        <x-indicador etiqueta="Unidades bloqueadas" :valor="$unidadesBloqueadas" detalle="Fuera del stock del catálogo" />
    </dl>

    <section class="mt-8">
        <h2 class="font-display text-xl font-bold text-coffee-800">Lotes por controlar</h2>

        <div class="mt-4 space-y-4">
            @forelse ($pendientes as $lote)
                <x-calidad-tarjeta :$lote />
            @empty
                <p class="rounded-2xl border border-dashed border-coffee-300 p-10 text-center text-sm text-coffee-700/60">
                    Todos los lotes registrados ya pasaron por control de calidad.
                </p>
            @endforelse
        </div>
    </section>

    <section class="mt-10">
        <h2 class="font-display text-xl font-bold text-coffee-800">Historial de controles</h2>
        <p class="mt-1 text-sm text-coffee-700/70">
            Trazabilidad de cada evaluación: quién la registró y con qué resultado.
        </p>

        <div class="mt-4 space-y-4">
            @forelse ($evaluados as $lote)
                <x-calidad-tarjeta :$lote />
            @empty
                <p class="rounded-2xl border border-dashed border-coffee-300 p-10 text-center text-sm text-coffee-700/60">
                    Todavía no se registró ningún control de calidad.
                </p>
            @endforelse
        </div>
    </section>

</x-layouts.app>
