<?php

namespace App\Http\Requests;

use App\Support\Carrito;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePedidoRequest extends FormRequest
{
    /**
     * Campos obligatorios del wireframe de Registro de Pedidos.
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
            'cliente_direccion' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
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
        ];
    }
}
