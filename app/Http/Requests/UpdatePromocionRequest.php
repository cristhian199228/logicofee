<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePromocionRequest extends FormRequest
{
    /**
     * Cada producto tiene su propio formulario en la pantalla, así que los
     * errores viajan en una bolsa propia y no se repiten en todas las filas.
     */
    protected function prepareForValidation(): void
    {
        /** @var Producto $producto */
        $producto = $this->route('producto');

        $this->errorBag = 'promocion-'.$producto->slug;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'destacado' => ['nullable', 'boolean'],
            'promocion_titulo' => ['nullable', 'string', 'max:60'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'quitar_banner' => ['nullable', 'boolean'],
            'descuento' => ['nullable', 'integer', 'min:0', 'max:70'],
            'promocion_inicia_at' => ['nullable', 'date'],
            'promocion_termina_at' => ['nullable', 'date', 'after_or_equal:promocion_inicia_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'destacado' => 'producto destacado',
            'promocion_titulo' => 'título de la promoción',
            'banner' => 'imagen del banner',
            'descuento' => 'descuento',
            'promocion_inicia_at' => 'inicio de la vigencia',
            'promocion_termina_at' => 'fin de la vigencia',
        ];
    }
}
