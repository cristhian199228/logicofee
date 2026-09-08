<?php

namespace App\Http\Requests;

use App\Models\Lote;
use Illuminate\Foundation\Http\FormRequest;

class StoreBajaLoteRequest extends FormRequest
{
    /**
     * Cada lote tiene su propio formulario de baja en la pantalla, así que los
     * errores viajan en una bolsa propia.
     */
    protected function prepareForValidation(): void
    {
        /** @var Lote $lote */
        $lote = $this->route('lote');

        $this->errorBag = 'baja-'.$lote->id;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Lote $lote */
        $lote = $this->route('lote');

        return [
            'cantidad' => ['required', 'integer', 'min:1', 'max:'.max($lote->cantidad_disponible, 1)],
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cantidad.max' => 'El lote solo tiene :max unidades disponibles para dar de baja.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cantidad' => 'cantidad',
            'motivo' => 'motivo de la baja',
        ];
    }
}
