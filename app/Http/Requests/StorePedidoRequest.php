<?php

namespace App\Http\Requests;

use App\Enums\MetodoPago;
use App\Enums\TipoEntrega;
use App\Support\Carrito;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StorePedidoRequest extends FormRequest
{
    /**
     * Campos obligatorios del wireframe de Registro de Pedidos, más la forma
     * de entrega y el medio con el que se paga.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'cliente_nombre' => ['required', 'string', 'max:255'],
            'cliente_telefono' => ['required', 'string', 'max:30'],
            'cliente_correo' => ['nullable', 'email', 'max:255'],
            'cliente_tipo' => ['required', Rule::in(config('logicoffee.tipos_cliente'))],
            'cliente_direccion' => [
                Rule::requiredIf(fn () => $this->string('tipo_entrega')->toString() === TipoEntrega::Delivery->value),
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
    public function messages(): array
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
    public function attributes(): array
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

    /**
     * @return array<int, callable>
     */
    public function after(Carrito $carrito): array
    {
        return [
            function (Validator $validator) use ($carrito) {
                if ($carrito->vacio()) {
                    $validator->errors()->add('carrito', 'Agrega al menos un producto antes de registrar el pedido.');
                }
            },
            function (Validator $validator) {
                if ($this->pagaConTarjeta() && $this->tarjetaVencida()) {
                    $validator->errors()->add('tarjeta_vencimiento', 'La tarjeta ya venció.');
                }
            },
        ];
    }

    private function pagaConTarjeta(): bool
    {
        return $this->string('metodo_pago')->toString() === MetodoPago::Tarjeta->value;
    }

    private function pagaConYape(): bool
    {
        return $this->string('metodo_pago')->toString() === MetodoPago::Yape->value;
    }

    /** El vencimiento llega como MM/AA y vale hasta el último día del mes. */
    private function tarjetaVencida(): bool
    {
        $vencimiento = $this->string('tarjeta_vencimiento')->toString();

        if (preg_match('#^(0[1-9]|1[0-2])/([0-9]{2})$#', $vencimiento, $partes) !== 1) {
            return false;
        }

        return Carbon::createFromDate(2000 + (int) $partes[2], (int) $partes[1], 1)
            ->endOfMonth()
            ->isPast();
    }
}
