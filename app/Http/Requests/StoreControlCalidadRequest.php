<?php

namespace App\Http\Requests;

use App\Enums\ResultadoCalidad;
use App\Models\Lote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreControlCalidadRequest extends FormRequest
{
    /**
     * Cada lote tiene su propio formulario en la pantalla, así que los errores
     * viajan en una bolsa propia.
     */
    protected function prepareForValidation(): void
    {
        /** @var Lote $lote */
        $lote = $this->route('lote');

        $this->errorBag = 'calidad-'.$lote->id;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'resultado' => ['required', Rule::enum(ResultadoCalidad::class)->only([
                ResultadoCalidad::Aprobado,
                ResultadoCalidad::Rechazado,
            ])],
            'calidad_nota' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'resultado' => 'resultado del control',
            'calidad_nota' => 'observación de calidad',
        ];
    }
}
