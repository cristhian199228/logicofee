<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoteRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'producto' => ['required', 'string', Rule::exists('productos', 'slug')],
            'codigo' => ['required', 'string', 'max:20', Rule::unique('lotes', 'codigo')],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'tostado_at' => ['required', 'date', 'before_or_equal:today'],
            'vence_at' => ['required', 'date', 'after:tostado_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'producto' => 'producto',
            'codigo' => 'código del lote',
            'cantidad' => 'cantidad',
            'tostado_at' => 'fecha de tostado',
            'vence_at' => 'fecha de vencimiento',
        ];
    }
}
