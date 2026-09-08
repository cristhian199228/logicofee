<?php

use App\Actions\RegistrarPedido;
use App\Enums\MetodoPago;
use App\Enums\Rol;
use App\Enums\Seccion;
use App\Enums\TipoEntrega;
use App\Models\Producto;
use App\Support\Carrito;
use App\Support\PasarelaPagoSimulada;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Registro del pedido con estado inicial "Pendiente" (HU02). Las cantidades,
 * el medio de pago y el resumen se recalculan en la misma pantalla.
 */
new #[Layout('components.layouts.app', ['titulo' => 'Nuevo pedido'])] class extends Component
{
    public string $cliente_nombre = '';

    public string $cliente_telefono = '';

    public string $cliente_correo = '';

    public string $cliente_tipo = '';

    public string $cliente_direccion = '';

    public string $observaciones = '';

    public string $tipo_entrega = '';

    public string $metodo_pago = '';

    public string $tarjeta_numero = '';

    public string $tarjeta_titular = '';

    public string $tarjeta_vencimiento = '';

    public string $tarjeta_cvv = '';

    public string $yape_celular = '';

    public ?string $aviso = null;

    public function mount(): void
    {
        $usuario = auth()->user();

        $this->cliente_nombre = $usuario->rol === Rol::Cliente ? $usuario->name : '';
        $this->cliente_tipo = config('logicoffee.tipos_cliente')[0];
        $this->tipo_entrega = TipoEntrega::Delivery->value;
        $this->metodo_pago = MetodoPago::Efectivo->value;
    }

    /**
     * Campos obligatorios del wireframe de Registro de Pedidos, más la forma
     * de entrega y el medio con el que se paga.
     *
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'cliente_nombre' => ['required', 'string', 'max:255'],
            'cliente_telefono' => ['required', 'string', 'max:30'],
            'cliente_correo' => ['nullable', 'email', 'max:255'],
            'cliente_tipo' => ['required', Rule::in(config('logicoffee.tipos_cliente'))],
            'cliente_direccion' => [
                Rule::requiredIf(fn () => $this->tipo_entrega === TipoEntrega::Delivery->value),
                'nullable', 'string', 'max:255',
            ],
            'observaciones' => ['nullable', 'string', 'max:1000'],

            'tipo_entrega' => ['required', Rule::enum(TipoEntrega::class)],
            'metodo_pago' => ['required', Rule::enum(MetodoPago::class)],

            'tarjeta_numero' => [Rule::requiredIf($this->pagaConTarjeta(...)), 'nullable', 'string', 'regex:/^[0-9 ]{13,23}$/'],
            'tarjeta_titular' => [Rule::requiredIf($this->pagaConTarjeta(...)), 'nullable', 'string', 'max:120'],
            'tarjeta_vencimiento' => [Rule::requiredIf($this->pagaConTarjeta(...)), 'nullable', 'string', 'regex:#^(0[1-9]|1[0-2])/[0-9]{2}$#'],
            'tarjeta_cvv' => [Rule::requiredIf($this->pagaConTarjeta(...)), 'nullable', 'string', 'digits_between:3,4'],

            'yape_celular' => [Rule::requiredIf($this->pagaConYape(...)), 'nullable', 'string', 'regex:/^9[0-9]{8}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'cliente_direccion.required' => 'El delivery necesita una dirección de entrega.',
            'tarjeta_numero.regex' => 'El número de la tarjeta debe tener entre 13 y 19 dígitos.',
            'tarjeta_vencimiento.regex' => 'El vencimiento va en formato MM/AA.',
            'yape_celular.regex' => 'El celular de Yape son 9 dígitos y empieza en 9.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'cliente_nombre' => 'nombre o razón social',
            'cliente_telefono' => 'teléfono de contacto',
            'cliente_correo' => 'correo electrónico',
            'cliente_tipo' => 'tipo de cliente',
            'cliente_direccion' => 'dirección de entrega',
            'tipo_entrega' => 'forma de entrega',
            'metodo_pago' => 'forma de pago',
            'tarjeta_numero' => 'número de la tarjeta',
            'tarjeta_titular' => 'titular de la tarjeta',
            'tarjeta_vencimiento' => 'vencimiento de la tarjeta',
            'tarjeta_cvv' => 'código de seguridad',
            'yape_celular' => 'celular de Yape',
        ];
    }

    #[Computed]
    public function carrito(): Carrito
    {
        return app(Carrito::class);
    }

    /** Envío que corresponde a la forma de entrega elegida ahora mismo. */
    #[Computed]
    public function envio(): float
    {
        if ($this->carrito()->vacio()) {
            return 0.0;
        }

        return TipoEntrega::tryFrom($this->tipo_entrega)?->costoEnvio() ?? 0.0;
    }

    #[Computed]
    public function total(): float
    {
        return $this->carrito()->subtotal() + $this->envio();
    }

    public function sumar(int $productoId): void
    {
        $this->ajustar($productoId, 1);
    }

    public function restar(int $productoId): void
    {
        $this->ajustar($productoId, -1);
    }

    public function quitar(int $productoId): void
    {
        $this->carrito()->quitar(Producto::findOrFail($productoId));

        $this->refrescarCarrito();
    }

    /**
     * Registra el pedido con estado inicial "Pendiente" y vuelve al catálogo
     * con la confirmación (HU02).
     */
    public function registrar(RegistrarPedido $registrar): void
    {
        abort_unless(auth()->user()->puedeVer(Seccion::Pedido), 403);

        $datos = $this->validador()->validate();

        $pedido = $registrar->handle($datos, auth()->user());

        // El tablero de seguimiento destaca el último pedido de esta sesión.
        session()->put('ultimo_pedido', $pedido->codigo);

        session()->flash('pedido_confirmado', [
            'codigo' => $pedido->codigo,
            'total' => (float) $pedido->total,
        ]);

        $this->redirectRoute('catalogo.index', navigate: true);
    }

    private function ajustar(int $productoId, int $delta): void
    {
        $this->carrito()->ajustar(Producto::findOrFail($productoId), $delta);

        $this->refrescarCarrito();
    }

    private function refrescarCarrito(): void
    {
        unset($this->carrito, $this->envio, $this->total);

        $this->dispatch('carrito-actualizado');
    }

    /**
     * Validador del formulario, con las dos reglas que no dependen de un solo
     * campo: el pedido necesita productos y la tarjeta no puede estar vencida.
     */
    private function validador(): \Illuminate\Validation\Validator
    {
        $reglas = $this->rules();

        $validador = Validator::make(
            $this->only(array_keys($reglas)),
            $reglas,
            $this->messages(),
            $this->validationAttributes(),
        );

        $validador->after(function (\Illuminate\Validation\Validator $validador): void {
            if ($this->carrito()->vacio()) {
                $validador->errors()->add('carrito', 'Agrega al menos un producto antes de registrar el pedido.');
            }

            if ($this->pagaConTarjeta() && $this->tarjetaVencida()) {
                $validador->errors()->add('tarjeta_vencimiento', 'La tarjeta ya venció.');
            }
        });

        return $validador;
    }

    private function pagaConTarjeta(): bool
    {
        return $this->metodo_pago === MetodoPago::Tarjeta->value;
    }

    private function pagaConYape(): bool
    {
        return $this->metodo_pago === MetodoPago::Yape->value;
    }

    /** El vencimiento llega como MM/AA y vale hasta el último día del mes. */
    private function tarjetaVencida(): bool
    {
        if (preg_match('#^(0[1-9]|1[0-2])/([0-9]{2})$#', $this->tarjeta_vencimiento, $partes) !== 1) {
            return false;
        }

        return Carbon::createFromDate(2000 + (int) $partes[2], (int) $partes[1], 1)
            ->endOfMonth()
            ->isPast();
    }
};
?>

@php($lineas = $this->carrito->lineas())

<div>
    <x-aviso :mensaje="$aviso" />

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-coffee-800">Nuevo pedido</h1>
            <p class="mt-1 text-sm text-coffee-700/70">
                Registra los datos del cliente y las cantidades. El pedido queda en estado "Pendiente".
            </p>
        </div>
        <a href="{{ route('catalogo.index') }}" wire:navigate
            class="rounded-full border border-coffee-300 bg-white px-4 py-2 text-sm font-semibold text-coffee-700 transition hover:border-coffee-500">
            ← Volver al catálogo
        </a>
    </div>

    <form wire:submit="registrar" class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-start">

        <div class="space-y-6">
            <section class="rounded-2xl border border-coffee-200 bg-coffee-50 p-6">
                <h2 class="font-display text-lg font-bold text-coffee-800">1 · Datos del cliente</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-campo-texto campo="cliente_nombre" etiqueta="Nombre / Razón social"
                        marcador="Ej. Restaurante Bella Vista" requerido />

                    <x-campo-texto campo="cliente_telefono" etiqueta="Teléfono de contacto"
                        marcador="Ej. 945 664 313" requerido />

                    <x-campo-texto campo="cliente_correo" etiqueta="Correo electrónico (opcional)" tipo="email"
                        marcador="Ej. contacto@negocio.com" />

                    <div>
                        <label for="cliente_tipo" class="block text-sm font-semibold text-coffee-800">Tipo de cliente</label>
                        <select id="cliente_tipo" wire:model="cliente_tipo"
                            class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15">
                            @foreach (config('logicoffee.tipos_cliente') as $tipo)
                                <option value="{{ $tipo }}">{{ $tipo }}</option>
                            @endforeach
                        </select>
                        @error('cliente_tipo')
                            <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <x-campo-texto campo="cliente_direccion" etiqueta="Dirección de entrega"
                            marcador="Ej. Av. Ejército 401, Yanahuara" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-coffee-200 bg-coffee-50 p-6">
                <h2 class="font-display text-lg font-bold text-coffee-800">2 · Productos y cantidades</h2>

                <div class="mt-4 space-y-3">
                    @forelse ($lineas as $linea)
                        @php($producto = $linea['producto'])

                        <div class="flex items-center gap-4 rounded-xl border border-coffee-200 bg-white p-3" wire:key="linea-{{ $producto->id }}">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-coffee-800">{{ $producto->nombre }}</p>
                                <p class="text-xs text-coffee-700/60">
                                    {{ $producto->presentacion }} · {{ $producto->categoria->etiqueta() }} ·
                                    <x-precio :valor="$producto->precioVigente()" /> c/u
                                    @if ($producto->tieneDescuento())
                                        <span class="ml-1 rounded bg-mostaza-400 px-1.5 py-0.5 font-bold text-coffee-900">-{{ $producto->descuento }}%</span>
                                    @endif
                                </p>
                            </div>

                            <div class="flex items-center gap-1 rounded-full border border-coffee-300 p-1">
                                <button type="button" wire:click="restar({{ $producto->id }})"
                                    aria-label="Quitar una unidad de {{ $producto->nombre }}"
                                    class="grid size-7 place-items-center rounded-full text-coffee-700 transition hover:bg-coffee-100">−</button>

                                <span class="w-8 text-center text-sm font-bold text-coffee-800">{{ $linea['cantidad'] }}</span>

                                <button type="button" wire:click="sumar({{ $producto->id }})"
                                    aria-label="Agregar una unidad de {{ $producto->nombre }}"
                                    @disabled($this->carrito->disponible($producto) <= 0)
                                    class="grid size-7 place-items-center rounded-full text-coffee-700 transition hover:bg-coffee-100 disabled:cursor-not-allowed disabled:text-coffee-700/25">+</button>
                            </div>

                            <x-precio :valor="$producto->precioVigente() * $linea['cantidad']"
                                class="w-20 text-right font-semibold text-coffee-800" />

                            <button type="button" wire:click="quitar({{ $producto->id }})"
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
                            <a href="{{ route('catalogo.index') }}" wire:navigate class="font-semibold text-coffee-600 underline">Ir al catálogo</a>
                        </p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-coffee-200 bg-coffee-50 p-6">
                <h2 class="font-display text-lg font-bold text-coffee-800">3 · Entrega y pago</h2>

                <fieldset class="mt-4">
                    <legend class="text-sm font-semibold text-coffee-800">¿Cómo se entrega?</legend>

                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        @foreach (TipoEntrega::cases() as $entrega)
                            <label class="relative block cursor-pointer">
                                <input type="radio" wire:model.live="tipo_entrega" value="{{ $entrega->value }}"
                                    class="peer sr-only" />
                                <span class="block rounded-xl border border-coffee-300 bg-white p-4 transition peer-checked:border-coffee-700 peer-checked:bg-coffee-100 peer-focus-visible:ring-4 peer-focus-visible:ring-coffee-500/20">
                                    <span class="flex items-baseline justify-between gap-2">
                                        <span class="font-semibold text-coffee-800">{{ $entrega->titulo() }}</span>
                                        <span class="text-sm font-bold text-coffee-700">
                                            {{ $entrega->costoEnvio() > 0 ? '$'.number_format($entrega->costoEnvio(), 2) : 'Sin cargo' }}
                                        </span>
                                    </span>
                                    <span class="mt-1 block text-xs text-coffee-700/60">{{ $entrega->descripcion() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('tipo_entrega')
                        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                    @enderror
                </fieldset>

                <fieldset class="mt-6">
                    <legend class="text-sm font-semibold text-coffee-800">¿Cómo se paga?</legend>

                    <div class="mt-2 grid gap-3 sm:grid-cols-3">
                        @foreach (MetodoPago::cases() as $metodo)
                            <label class="relative block cursor-pointer">
                                <input type="radio" wire:model.live="metodo_pago" value="{{ $metodo->value }}"
                                    class="peer sr-only" />
                                <span class="block h-full rounded-xl border border-coffee-300 bg-white p-4 transition peer-checked:border-coffee-700 peer-checked:bg-coffee-100 peer-focus-visible:ring-4 peer-focus-visible:ring-coffee-500/20">
                                    <span class="font-semibold text-coffee-800">{{ $metodo->value }}</span>
                                    <span class="mt-1 block text-xs text-coffee-700/60">{{ $metodo->descripcion() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('metodo_pago')
                        <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                    @enderror
                </fieldset>

                {{-- Solo se piden los datos del medio de pago que el cliente eligió. --}}
                @if ($metodo_pago === MetodoPago::Tarjeta->value)
                    <div class="mt-4 grid gap-4 rounded-xl border border-coffee-300 bg-white p-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="tarjeta_numero" class="block text-sm font-semibold text-coffee-800">Número de la tarjeta</label>
                            <input type="text" id="tarjeta_numero" wire:model="tarjeta_numero" inputmode="numeric" autocomplete="off"
                                placeholder="4242 4242 4242 4242"
                                class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 font-mono text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                            @error('tarjeta_numero')
                                <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="tarjeta_titular" class="block text-sm font-semibold text-coffee-800">Titular</label>
                            <input type="text" id="tarjeta_titular" wire:model="tarjeta_titular" autocomplete="off"
                                placeholder="Como aparece en la tarjeta"
                                class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                            @error('tarjeta_titular')
                                <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="tarjeta_vencimiento" class="block text-sm font-semibold text-coffee-800">Vencimiento</label>
                            <input type="text" id="tarjeta_vencimiento" wire:model="tarjeta_vencimiento" inputmode="numeric"
                                placeholder="MM/AA" maxlength="5"
                                class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 font-mono text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                            @error('tarjeta_vencimiento')
                                <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="tarjeta_cvv" class="block text-sm font-semibold text-coffee-800">Código de seguridad</label>
                            <input type="text" id="tarjeta_cvv" wire:model="tarjeta_cvv" inputmode="numeric" autocomplete="off"
                                placeholder="123" maxlength="4"
                                class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 font-mono text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                            @error('tarjeta_cvv')
                                <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif

                @if ($metodo_pago === MetodoPago::Yape->value)
                    <div class="mt-4 rounded-xl border border-coffee-300 bg-white p-4">
                        <div class="flex flex-wrap items-start gap-4">
                            <span class="grid size-20 shrink-0 place-items-center rounded-xl bg-[#742384] text-center font-display text-sm font-bold leading-tight text-white">
                                Yape<br />QR
                            </span>

                            <div class="min-w-0 flex-1">
                                <label for="yape_celular" class="block text-sm font-semibold text-coffee-800">Celular con Yape</label>
                                <input type="text" id="yape_celular" wire:model="yape_celular" inputmode="numeric" maxlength="9"
                                    placeholder="987654321"
                                    class="mt-1.5 w-full rounded-xl border border-coffee-300 bg-white px-4 py-2.5 font-mono text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15" />
                                @error('yape_celular')
                                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-coffee-700/60">
                                    Al confirmar se genera el código de operación de la transferencia.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <p class="mt-4 rounded-xl border border-coffee-300 bg-white px-4 py-3 text-xs leading-relaxed text-coffee-700/70">
                    <strong class="font-bold text-coffee-800">Pago simulado.</strong>
                    No se conecta con ningún banco: cualquier tarjeta o número de Yape se aprueba y devuelve un código
                    de operación. Para probar un rechazo usa un medio terminado en
                    <span class="font-mono font-bold text-ladrillo-500">{{ PasarelaPagoSimulada::TERMINACION_RECHAZADA }}</span>.
                </p>
            </section>

            <section class="rounded-2xl border border-coffee-200 bg-coffee-50 p-6">
                <h2 class="font-display text-lg font-bold text-coffee-800">4 · Observaciones</h2>
                <label for="observaciones" class="sr-only">Observaciones del pedido</label>
                <textarea id="observaciones" wire:model="observaciones" rows="3"
                    placeholder="Notas de entrega, molienda, horario…"
                    class="mt-4 w-full rounded-xl border border-coffee-300 bg-white px-4 py-3 text-coffee-900 placeholder:text-coffee-700/40 transition focus:border-coffee-500 focus:outline-none focus:ring-4 focus:ring-coffee-500/15"></textarea>
                @error('observaciones')
                    <p class="mt-1.5 text-xs font-semibold text-ladrillo-500">{{ $message }}</p>
                @enderror
            </section>
        </div>

        <aside class="rounded-2xl border-2 border-coffee-300 bg-coffee-50 p-6 lg:sticky lg:top-24">
            <h2 class="font-display text-lg font-bold text-coffee-800">Resumen</h2>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between text-coffee-700/70">
                    <dt>{{ $this->carrito->productosDistintos() }} {{ $this->carrito->productosDistintos() === 1 ? 'producto' : 'productos' }}</dt>
                    <dd>{{ $this->carrito->unidades() }} uds</dd>
                </div>
                <div class="flex justify-between text-coffee-800">
                    <dt>Subtotal</dt>
                    <dd><x-precio :valor="$this->carrito->subtotal()" class="font-semibold" /></dd>
                </div>
                <div class="flex justify-between text-coffee-800">
                    <dt>Envío</dt>
                    <dd class="font-semibold">
                        @if ($this->envio > 0)
                            <x-precio :valor="$this->envio" />
                        @else
                            Sin cargo
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="mt-4 flex items-baseline justify-between border-t border-coffee-300 pt-4">
                <span class="font-display text-lg font-bold text-coffee-800">Total</span>
                <x-precio :valor="$this->total" class="font-display text-2xl font-bold text-coffee-800" />
            </div>

            <div class="mt-5 rounded-xl border border-coffee-300 bg-white px-4 py-3 text-sm text-coffee-800">
                Estado inicial
                <span class="ml-1 rounded bg-mostaza-400 px-2 py-0.5 font-bold text-coffee-900">Pendiente</span>
            </div>

            <div class="mt-5 space-y-3">
                @if ($this->carrito->vacio())
                    <p class="rounded-xl border border-mostaza-500/40 bg-mostaza-400/15 px-4 py-3 text-xs leading-relaxed text-coffee-800">
                        Para registrar el pedido falta al menos un producto.
                    </p>
                @endif

                @error('pago')
                    <p class="rounded-xl border border-ladrillo-500/30 bg-ladrillo-500/10 px-4 py-3 text-xs font-semibold leading-relaxed text-ladrillo-500" role="alert">
                        {{ $message }}
                    </p>
                @enderror

                @error('carrito')
                    <p class="rounded-xl border border-ladrillo-500/30 bg-ladrillo-500/10 px-4 py-3 text-xs font-semibold leading-relaxed text-ladrillo-500" role="alert">
                        {{ $message }}
                    </p>
                @enderror

                <button type="submit" @disabled($this->carrito->vacio()) wire:loading.attr="disabled" wire:target="registrar"
                    class="w-full rounded-xl bg-mostaza-500 py-3.5 font-bold text-coffee-900 shadow-lg shadow-mostaza-500/25 transition hover:bg-mostaza-400 focus:outline-none focus-visible:ring-4 focus-visible:ring-mostaza-500/30 active:scale-[.99] disabled:cursor-not-allowed disabled:bg-coffee-200 disabled:text-coffee-700/40 disabled:shadow-none">
                    <span wire:loading.remove wire:target="registrar">Confirmar Pedido (Pendiente)</span>
                    <span wire:loading wire:target="registrar">Registrando…</span>
                </button>

                <a href="{{ route('catalogo.index') }}" wire:navigate
                    class="block w-full rounded-xl border border-coffee-300 bg-white py-3 text-center font-semibold text-coffee-700 transition hover:border-coffee-500 focus:outline-none focus-visible:ring-4 focus-visible:ring-coffee-500/20">
                    Seguir Comprando
                </a>
            </div>
        </aside>
    </form>
</div>
