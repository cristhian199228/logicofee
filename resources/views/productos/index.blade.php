<x-layouts.app titulo="Productos">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Catálogo de cafés</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Da de alta cafés, edita sus datos y su foto. El stock no se toca aquí: sale de los lotes del almacén.
            </p>
        </div>
        <span class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700">Gestión de catálogo</span>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-indicador etiqueta="Productos" :valor="$productos->count()" detalle="Publicados en el catálogo" />
        <x-indicador etiqueta="En promoción" :valor="$destacados" detalle="Marcados como destacados" />
        <x-indicador etiqueta="Agotados o en el mínimo" :valor="$bajoStock"
            detalle="Necesitan reposición"
            :tono="$bajoStock > 0 ? 'aviso' : 'neutro'" />
    </dl>

    <section class="mt-8 rounded-2xl border-2 border-coffee-300 bg-coffee-50 p-6">
        <h2 class="font-display text-lg font-bold text-coffee-800">Agregar café al catálogo</h2>

        <form method="POST" action="{{ route('productos.store') }}" enctype="multipart/form-data"
            class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @csrf

            <x-campo-texto nombre="nombre" etiqueta="Nombre" marcador="Ej. Bourbon Salvador" requerido />
            <x-campo-texto nombre="presentacion" etiqueta="Presentación" marcador="Ej. 500 g" requerido />

            <div>
                <label for="categoria" class="block text-sm font-semibold text-coffee-800">
                    Categoría <span class="text-ladrillo-500" aria-hidden="true">*</span>
                </label>
                <select id="categoria" name="categoria"
                    class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15">
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->value }}" @selected(old('categoria') === $categoria->value)>
                            {{ $categoria->etiqueta() }}
                        </option>
                    @endforeach
                </select>
                @error('categoria')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="precio" class="block text-sm font-semibold text-coffee-800">
                    Precio <span class="text-ladrillo-500" aria-hidden="true">*</span>
                </label>
                <input type="number" id="precio" name="precio" step="0.01" min="0.1" required
                    value="{{ old('precio') }}" placeholder="Ej. 18.50"
                    class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                @error('precio')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="stock_minimo" class="block text-sm font-semibold text-coffee-800">
                    Stock mínimo <span class="text-ladrillo-500" aria-hidden="true">*</span>
                </label>
                <input type="number" id="stock_minimo" name="stock_minimo" min="0" required
                    value="{{ old('stock_minimo', 15) }}"
                    class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                @error('stock_minimo')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="acento" class="block text-sm font-semibold text-coffee-800">Color del empaque</label>
                <input type="color" id="acento" name="acento" value="{{ old('acento', '#4a7c3f') }}"
                    class="mt-1.5 h-11 w-full cursor-pointer rounded-xl border border-coffee-300 bg-white px-2 py-1" />
                @error('acento')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="descripcion" class="block text-sm font-semibold text-coffee-800">
                    Descripción <span class="text-ladrillo-500" aria-hidden="true">*</span>
                </label>
                <textarea id="descripcion" name="descripcion" rows="2" required
                    placeholder="Notas de cata, origen, tueste..."
                    class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15">{{ old('descripcion') }}</textarea>
                @error('descripcion')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="foto" class="block text-sm font-semibold text-coffee-800">Foto (opcional)</label>
                <input type="file" id="foto" name="foto" accept="image/*"
                    class="mt-2.5 w-full text-xs text-coffee-700/60 file:mr-2 file:cursor-pointer file:rounded-full file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-700 hover:file:bg-coffee-200" />
                @error('foto')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <button type="submit"
                    class="rounded-xl bg-coffee-500 px-6 py-3 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30">
                    Agregar al catálogo
                </button>
            </div>
        </form>
    </section>

    <div class="mt-8 space-y-5">
        @foreach ($productos as $producto)
            @php($bolsa = 'producto-'.$producto->slug)
            @php($fallo = $errors->getBag($bolsa)->any())

            <article @class([
                'rounded-2xl border bg-white p-6',
                'border-coffee-200' => ! $producto->agotado(),
                'border-ladrillo-500/40' => $producto->agotado(),
            ])>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <x-producto-imagen :$producto marco="size-16 shrink-0 rounded-xl" ilustracion="h-10 w-auto" />

                        <div class="min-w-0">
                            <h2 class="font-display text-lg font-bold text-coffee-800">
                                {{ $producto->nombre }}
                                @if ($producto->promocionVigente())
                                    <span class="ml-1 rounded-full bg-mostaza-500 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-coffee-900">
                                        En promoción
                                    </span>
                                @endif
                            </h2>
                            <p class="text-xs text-coffee-700/60">
                                {{ $producto->slug }} · {{ $producto->lotes_count }}
                                {{ $producto->lotes_count === 1 ? 'lote' : 'lotes' }} ·
                                <x-precio :valor="$producto->precio" /> de lista
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span @class([
                            'rounded-full px-3 py-1 text-xs font-bold',
                            'bg-ladrillo-500/10 text-ladrillo-500' => $producto->agotado(),
                            'bg-mostaza-400/25 text-coffee-900' => ! $producto->agotado() && $producto->bajoStock(),
                            'bg-coffee-100 text-coffee-700' => ! $producto->agotado() && ! $producto->bajoStock(),
                        ])>
                            {{ $producto->stock }} uds · mínimo {{ $producto->stock_minimo }}
                        </span>

                        <form method="POST" action="{{ route('productos.destroy', $producto) }}">
                            @csrf @method('DELETE')
                            <button type="submit"
                                class="rounded-full border-2 border-ladrillo-500 px-4 py-1.5 text-sm font-semibold text-ladrillo-500 transition hover:bg-ladrillo-500 hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-ladrillo-500/25">
                                Retirar
                            </button>
                        </form>
                    </div>
                </div>

                @error('eliminar', $bolsa)
                    <p class="mt-3 rounded-xl border border-ladrillo-500/40 bg-ladrillo-500/5 px-4 py-2 text-sm font-semibold text-ladrillo-500">
                        {{ $message }}
                    </p>
                @enderror

                <form method="POST" action="{{ route('productos.update', $producto) }}" enctype="multipart/form-data"
                    class="mt-5 grid gap-4 border-t border-coffee-200 pt-5 sm:grid-cols-2 lg:grid-cols-3">
                    @csrf @method('PATCH')

                    <div>
                        <label for="nombre-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Nombre</label>
                        <input type="text" id="nombre-{{ $producto->slug }}" name="nombre" required
                            value="{{ $fallo ? old('nombre') : $producto->nombre }}"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        @error('nombre', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="presentacion-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Presentación</label>
                        <input type="text" id="presentacion-{{ $producto->slug }}" name="presentacion" required
                            value="{{ $fallo ? old('presentacion') : $producto->presentacion }}"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        @error('presentacion', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="categoria-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Categoría</label>
                        <select id="categoria-{{ $producto->slug }}" name="categoria"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15">
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria->value }}"
                                    @selected(($fallo ? old('categoria') : $producto->categoria->value) === $categoria->value)>
                                    {{ $categoria->etiqueta() }}
                                </option>
                            @endforeach
                        </select>
                        @error('categoria', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="precio-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Precio</label>
                        <input type="number" id="precio-{{ $producto->slug }}" name="precio" step="0.01" min="0.1" required
                            value="{{ $fallo ? old('precio') : $producto->precio }}"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        @error('precio', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="minimo-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Stock mínimo</label>
                        <input type="number" id="minimo-{{ $producto->slug }}" name="stock_minimo" min="0" required
                            value="{{ $fallo ? old('stock_minimo') : $producto->stock_minimo }}"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                        @error('stock_minimo', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="acento-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Color del empaque</label>
                        <input type="color" id="acento-{{ $producto->slug }}" name="acento"
                            value="{{ $fallo ? old('acento') : $producto->acento }}"
                            class="mt-1.5 h-11 w-full cursor-pointer rounded-xl border border-coffee-300 bg-white px-2 py-1" />
                        @error('acento', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="descripcion-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">Descripción</label>
                        <textarea id="descripcion-{{ $producto->slug }}" name="descripcion" rows="2" required
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15">{{ $fallo ? old('descripcion') : $producto->descripcion }}</textarea>
                        @error('descripcion', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="foto-producto-{{ $producto->slug }}" class="block text-sm font-semibold text-coffee-800">
                            Cambiar foto
                        </label>
                        <input type="file" id="foto-producto-{{ $producto->slug }}" name="foto" accept="image/*"
                            class="mt-2.5 w-full text-xs text-coffee-700/60 file:mr-2 file:cursor-pointer file:rounded-full file:border-0 file:bg-coffee-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-coffee-700 hover:file:bg-coffee-200" />
                        @error('foto', $bolsa)
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-3">
                        <button type="submit"
                            class="rounded-xl bg-coffee-500 px-6 py-3 font-semibold text-white shadow-lg shadow-coffee-500/25 transition hover:bg-coffee-600 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/30">
                            Guardar cambios
                        </button>

                        @if ($producto->tieneFoto())
                            <span class="text-xs text-coffee-700/50">
                                La foto actual se reemplaza solo si eliges una nueva.
                            </span>
                        @endif
                    </div>
                </form>
            </article>
        @endforeach
    </div>

</x-layouts.app>
