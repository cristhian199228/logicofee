<?php

namespace App\Http\Requests;

use App\Enums\CategoriaProducto;
use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductoRequest extends FormRequest
{
    /**
     * Cada producto tiene su propio formulario en la pantalla, así que los
     * errores viajan en una bolsa propia.
     */
    protected function prepareForValidation(): void
    {
        /** @var Producto $producto */
        $producto = $this->route('producto');

        $this->errorBag = 'producto-'.$producto->slug;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'presentacion' => ['required', 'string', 'max:30'],
            'categoria' => ['required', Rule::enum(CategoriaProducto::class)],
            'descripcion' => ['required', 'string', 'max:500'],
            'precio' => ['required', 'numeric', 'min:0.1', 'max:9999.99'],
            'stock_minimo' => ['required', 'integer', 'min:0', 'max:10000'],
            'acento' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'acento.regex' => 'El color de acento debe ser un hexadecimal como #4a7c3f.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'presentacion' => 'presentación',
            'categoria' => 'categoría',
            'descripcion' => 'descripción',
            'precio' => 'precio',
            'stock_minimo' => 'stock mínimo',
            'acento' => 'color de acento',
            'foto' => 'foto del producto',
        ];
    }
}
