<x-layouts.app titulo="Nuevo pedido">

    @php($lineas = $carrito->lineas())

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Nuevo pedido</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Registra los datos del cliente y las cantidades. El pedido queda en estado "Pendiente".
            </p>
        </div>
        <a href="{{ route('catalogo.index') }}"
            class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700 transition hover:border-coffee-500">
            ← Volver al catálogo
        </a>
    </div>

    <form method="POST" action="{{ route('pedidos.store') }}"
        class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-start">
        @csrf

        <div class="space-y-6">
            <section class="rounded-2xl border border-coffee-200 bg-coffee-50 p-6">
                <h2 class="font-display text-lg font-bold text-coffee-800">1 · Datos del cliente</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-campo-texto nombre="cliente_nombre" etiqueta="Nombre / Razón social"
                        :valor="$usuario->rol === \App\Enums\Rol::Cliente ? $usuario->name : ''"
                        marcador="Ej. Restaurante Bella Vista" requerido />

                    <x-campo-texto nombre="cliente_telefono" etiqueta="Teléfono de contacto"
                        marcador="Ej. 945 664 313" requerido />

                    <x-campo-texto nombre="cliente_correo" etiqueta="Correo electrónico (opcional)" tipo="email"
                        marcador="Ej. contacto@negocio.com" />

                    <div>
                        <label for="cliente_tipo" class="block text-sm font-semibold text-coffee-800">Tipo de cliente</label>
                        <select id="cliente_tipo" name="cliente_tipo"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15">
                            @foreach (config('logicoffee.tipos_cliente') as $tipo)
                                <option value="{{ $tipo }}" @selected(old('cliente_tipo') === $tipo)>{{ $tipo }}</option>
                            @endforeach
                        </select>
                        @error('cliente_tipo')
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <x-campo-texto nombre="cliente_direccion" etiqueta="Dirección de entrega"
                            marcador="Ej. Av. Ejército 401, Yanahuara" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-coffee-200 bg-coffee-50 p-6">
                <h2 class="font-display text-lg font-bold text-coffee-800">2 · Productos y cantidades</h2>

                <div class="mt-4 space-y-3">
                    @forelse ($lineas as $linea)
                        @php($producto = $linea['producto'])

                        <div class="flex items-center gap-4 rounded-xl border border-coffee-200 bg-white p-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-coffee-800">{{ $producto->nombre }}</p>
                                <p class="text-xs text-coffee-700/60">
                                    {{ $producto->presentacion }} · {{ $producto->categoria->etiqueta() }} ·
                                    <x-precio :valor="$producto->precio" /> c/u
                                </p>
                            </div>

                            <div class="flex items-center gap-1 rounded-full border border-coffee-300 p-1">
                                <button type="submit" form="carrito-menos-{{ $producto->id }}"
                                    aria-label="Quitar una unidad de {{ $producto->nombre }}"
                                    class="grid size-7 place-items-center rounded-full text-coffee-700 transition hover:bg-coffee-100">−</button>

                                <span class="w-8 text-center text-sm font-bold text-coffee-800">{{ $linea['cantidad'] }}</span>

                                <button type="submit" form="carrito-mas-{{ $producto->id }}"
                                    aria-label="Agregar una unidad de {{ $producto->nombre }}"
                                    @disabled($carrito->disponible($producto) <= 0)
                                    class="grid size-7 place-items-center rounded-full text-coffee-700 transition hover:bg-coffee-100 disabled:cursor-not-allowed disabled:text-coffee-700/25">+</button>
                            </div>

                            <x-precio :valor="(float) $producto->precio * $linea['cantidad']"
                                class="w-20 text-right font-semibold text-coffee-800" />

                            <button type="submit" form="carrito-quitar-{{ $producto->id }}"
                                aria-label="Eliminar {{ $producto->nombre }} del pedido"
                                class="grid size-8 place-items-center rounded-lg text-ladrillo-500 transition hover:bg-ladrillo-500/10">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                    <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
                                </svg>
                            </button>
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-coffee-300 p-8 text-center text-sm text-coffee-700/60">
                            Aún no agregaste productos.
                            <a href="{{ route('catalogo.index') }}" class="font-semibold text-coffee-600 underline">Ir al catálogo</a>
                        </p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-coffee-200 bg-coffee-50 p-6">
                <h2 class="font-display text-lg font-bold text-coffee-800">3 · Observaciones</h2>
                <label for="observaciones" class="sr-only">Observaciones del pedido</label>
                <textarea id="observaciones" name="observaciones" rows="3"
                    placeholder="Notas de entrega, molienda, horario…"
                    class="mt-4 w-full rounded-xl border border-coffee-300 bg-white px-4 py-3 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15">{{ old('observaciones') }}</textarea>
                @error('observaciones')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </section>
        </div>

        <aside class="rounded-2xl border-2 border-coffee-300 bg-coffee-50 p-6 lg:sticky lg:top-24">
            <h2 class="font-display text-lg font-bold text-coffee-800">Resumen</h2>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between text-coffee-700/70">
                    <dt>{{ $carrito->productosDistintos() }} {{ $carrito->productosDistintos() === 1 ? 'producto' : 'productos' }}</dt>
                    <dd>{{ $carrito->unidades() }} uds</dd>
                </div>
                <div class="flex justify-between text-coffee-800">
                    <dt>Subtotal</dt>
                    <dd><x-precio :valor="$carrito->subtotal()" class="font-semibold" /></dd>
                </div>
                <div class="flex justify-between text-coffee-800">
                    <dt>Envío</dt>
                    <dd class="font-semibold">
                        @if ($carrito->vacio())
                            Sin cargo
                        @else
                            <x-precio :valor="$carrito->envio()" />
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="mt-4 flex items-baseline justify-between border-t border-coffee-300 pt-4">
                <span class="font-display text-lg font-bold text-coffee-800">Total</span>
                <x-precio :valor="$carrito->total()" class="font-display text-2xl font-bold text-coffee-800" />
            </div>

            <div class="mt-5 rounded-xl border border-coffee-300 bg-white px-4 py-3 text-sm text-coffee-800">
                Estado inicial
                <span class="ml-1 rounded bg-mostaza-400 px-2 py-0.5 font-bold text-coffee-900">Pendiente</span>
            </div>

            <div class="mt-5 space-y-3">
                @if ($carrito->vacio())
                    <p class="rounded-xl border border-mostaza-500/40 bg-mostaza-400/15 px-4 py-3 text-xs leading-relaxed text-coffee-800">
                        Para registrar el pedido falta al menos un producto.
                    </p>
                @endif

                @error('carrito')
                    <p class="rounded-xl border border-ladrillo-500/30 bg-ladrillo-500/10 px-4 py-3 text-xs font-semibold leading-relaxed text-ladrillo-500" role="alert">
                        {{ $message }}
                    </p>
                @enderror

                <button type="submit" @disabled($carrito->vacio())
                    class="w-full rounded-xl bg-mostaza-500 py-3.5 font-bold text-coffee-900 shadow-lg shadow-mostaza-500/25 transition hover:bg-mostaza-400 focus:outline-none focus-visible:ring-4 focus-visible:ring-mostaza-500/30 active:scale-[.99] disabled:cursor-not-allowed disabled:bg-coffee-200 disabled:text-coffee-700/40 disabled:shadow-none">
                    Confirmar Pedido (Pendiente)
                </button>

                <a href="{{ route('catalogo.index') }}"
                    class="block w-full rounded-xl border border-coffee-300 bg-white py-3 text-center font-semibold text-coffee-700 transition hover:border-coffee-500 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/20">
                    Seguir Comprando
                </a>
            </div>
        </aside>
    </form>

    {{-- Los controles del carrito viven fuera del formulario del pedido: HTML no
         permite anidar formularios, así que se enlazan con el atributo "form". --}}
    @foreach ($lineas as $linea)
        @php($producto = $linea['producto'])

        <form id="carrito-mas-{{ $producto->id }}" method="POST" action="{{ route('carrito.update', $producto) }}" hidden>
            @csrf @method('PATCH')
            <input type="hidden" name="delta" value="1" />
        </form>

        <form id="carrito-menos-{{ $producto->id }}" method="POST" action="{{ route('carrito.update', $producto) }}" hidden>
            @csrf @method('PATCH')
            <input type="hidden" name="delta" value="-1" />
        </form>

        <form id="carrito-quitar-{{ $producto->id }}" method="POST" action="{{ route('carrito.destroy', $producto) }}" hidden>
            @csrf @method('DELETE')
        </form>
    @endforeach

</x-layouts.app>
