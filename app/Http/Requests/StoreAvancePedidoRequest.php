<?php

namespace App\Http\Requests;

use App\Enums\EstadoPedido;
use App\Models\Pedido;
use Illuminate\Foundation\Http\FormRequest;

class StoreAvancePedidoRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'recibido_por' => ['nullable', 'string', 'max:120'],
            'cobrado' => ['nullable', 'boolean'],
        ];
    }

    /** Solo la entrega anota quién recibió el pedido y si se cobró ahí mismo. */
    public function esEntrega(): bool
    {
        /** @var Pedido $pedido */
        $pedido = $this->route('pedido');

        return $pedido->estado->siguiente() === EstadoPedido::Entregado;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'recibido_por' => 'persona que recibe',
            'cobrado' => 'cobro en la entrega',
        ];
    }
}
