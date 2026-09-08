<x-layouts.app titulo="Promociones">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Promociones del catálogo</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Destaca productos, define el descuento y la vigencia con la que aparecen en el catálogo.
            </p>
        </div>
        <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Marketing y ventas</span>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-indicador etiqueta="Promociones vigentes" :valor="$vigentes->count()"
            detalle="Se muestran ahora en el banner del catálogo"
            :tono="$vigentes->isEmpty() ? 'neutro' : 'aviso'" />
        <x-indicador etiqueta="Programadas o vencidas" :valor="$programadas->count()"
            detalle="Destacadas fuera de su rango de fechas" />
        <x-indicador etiqueta="Productos en catálogo" :valor="$productos->count()" />
    </dl>

    <div class="mt-8 space-y-5">
        @foreach ($productos as $producto)
            @php($bolsa = 'promocion-'.$producto->slug)
            {{-- Solo el formulario que falló repuebla con lo que se envió. --}}
            @php($fallo = $errors->getBag($bolsa)->any())

            <article @class([
                'rounded-2xl border bg-white p-6',
                'border-mostaza-500' => $producto->promocionVigente(),
                'border-coffee-200' => ! $producto->promocionVigente(),
            ])>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <x-producto-imagen :$producto marco="size-16 shrink-0 rounded-xl" ilustracion="h-10 w-auto" />
                        <div class="min-w-0">
                            <h2 class="font-display text-lg font-bold text-coffee-800">{{ $producto->nombre }}</h2>
                            <p class="text-xs text-coffee-700/60">
                                {{ $producto->presentacion }} · {{ $producto->categoria->etiqueta() }} ·
                                <x-precio :valor="$producto->precio" /> de lista
                            </p>
                        </div>
                    </div>

                    <div class="text-right">
                        @if ($producto->promocionVigente())
                            <span class="rounded-full bg-mostaza-500 px-3 py-1 text-xs font-bold uppercase tracking-wide text-coffee-900">
                                {{ $producto->tieneDescuento() ? 'En promoción · -'.$producto->descuento.'%' : 'Destacado' }}
                            </span>
                            <p class="mt-1 text-sm font-semibold text-coffee-800">
                                Precio promocional <x-precio :valor="$producto->precioVigente()" />
                            </p>
                        @elseif ($producto->destacado)
                            <span class="rounded-full bg-coffee-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-coffee-700/70">
                                Fuera de vigencia
                            </span>
                        @else
                            <span class="rounded-full bg-coffee-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-coffee-700/50">
                                Sin promoción
                            </span>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('promociones.update', $producto) }}"
                    enctype="multipart/form-data"
                    class="mt-5 grid gap-4 border-t border-coffee-200 pt-5 sm:grid-cols-2 lg:grid-cols-5">
                    @csrf @method('PATCH')

                    <label class="flex items-center gap-2 rounded-xl border border-coffee-300 bg-coffee-50 px-4 py-2.5 lg:col-span-1">
                        <input type="checkbox" name="destacado" value="1" @checked($producto->destacado)
                            class="size-4 rounded border-coffee-300 text-coffee-600 focus:ring-coffee-500/30" />
                        <span class="text-sm font-semibold text-coffee-800">Destacado</span>
                    </label>

                    <div class="lg:col-span-2">
                        <label for="titulo-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">
                            Título de la promoción
                        </label>
                        <input type="text" id="titulo-{{ $producto->slug }}" name="promocion_titulo"
                            value="{{ $fallo ? old('promocion_titulo') : $producto->promocion_titulo }}"
                            placeholder="Ej. Semana del café de origen"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        @error('promocion_titulo', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="descuento-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">
                            Descuento (%)
                        </label>
                        <input type="number" id="descuento-{{ $producto->slug }}" name="descuento" min="0" max="70"
                            value="{{ $fallo ? old('descuento') : $producto->descuento }}"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        @error('descuento', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="inicia-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Desde</label>
                            <input type="date" id="inicia-{{ $producto->slug }}" name="promocion_inicia_at"
                                value="{{ $fallo ? old('promocion_inicia_at') : $producto->promocion_inicia_at?->toDateString() }}"
                                class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-3 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        </div>
                        <div>
                            <label for="termina-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Hasta</label>
                            <input type="date" id="termina-{{ $producto->slug }}" name="promocion_termina_at"
                                value="{{ $fallo ? old('promocion_termina_at') : $producto->promocion_termina_at?->toDateString() }}"
                                class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-3 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        </div>
                        @error('promocion_termina_at', $bolsa)
                            <p class="col-span-2 mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2 lg:col-span-5">
                        <p class="text-sm font-semibold text-coffee-800">Imagen del banner</p>
                        <p class="mt-0.5 text-xs text-coffee-700/60">
                            Encabeza la sección de promociones del catálogo. Se ve mejor apaisada (16:9), hasta 4 MB.
                        </p>

                        <div class="mt-2 flex flex-wrap items-center gap-4">
                            @if ($producto->tieneBanner())
                                <img src="{{ $producto->urlBanner() }}" alt="Banner de {{ $producto->nombre }}"
                                    loading="lazy" class="h-20 w-36 shrink-0 rounded-xl object-cover object-center" />
                            @else
                                <span class="grid h-20 w-36 shrink-0 place-items-center rounded-xl border border-dashed border-coffee-300 text-xs text-coffee-700/40">
                                    Sin banner
                                </span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <label for="banner-{{ $producto->slug }}" class="sr-only">Banner de {{ $producto->nombre }}</label>
                                <input type="file" id="banner-{{ $producto->slug }}" name="banner" accept="image/*"
                                    class="w-full text-xs text-coffee-700/60 file:mr-2 file:cursor-pointer file:rounded-full file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-700 hover:file:bg-coffee-200" />

                                @if ($producto->tieneBanner())
                                    <label class="mt-2 flex items-center gap-2 text-xs font-semibold text-ladrillo-500">
                                        <input type="checkbox" name="quitar_banner" value="1"
                                            class="size-4 rounded border-coffee-300 text-ladrillo-500 focus:ring-ladrillo-500/30" />
                                        Quitar el banner actual
                                    </label>
                                @endif

                                @error('banner', $bolsa)
                                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-5">
                        <button type="submit"
                            class="rounded-xl bg-coffee-500 px-6 py-3 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30">
                            Guardar promoción
                        </button>
                    </div>
                </form>
            </article>
        @endforeach
    </div>

</x-layouts.app>
