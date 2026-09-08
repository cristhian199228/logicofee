<?php

namespace Database\Seeders;

use App\Enums\CategoriaProducto;
use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ProductoSeeder extends Seeder
{
    /** Fotos de prueba que acompañan al catálogo inicial. */
    private const ORIGEN_FOTOS = __DIR__.'/fotos';

    private const DESTINO_FOTOS = 'productos';

    /**
     * Catálogo inicial de LogiCoffee. El stock queda en cero: lo aportan los
     * lotes que registra el LoteSeeder. Dos productos arrancan destacados en
     * la sección de promociones del catálogo (HU03).
     */
    public function run(): void
    {
        $productos = [
            [
                'slug' => 'bourbon-250',
                'nombre' => 'Café Bourbon Salvador',
                'presentacion' => '250 g',
                'categoria' => CategoriaProducto::EnGrano,
                'descripcion' => 'Granos seleccionados con notas dulces a chocolate artesanal y miel silvestre. Acidez cítrica brillante.',
                'precio' => 14.50,
                'stock_minimo' => 30,
                'acento' => '#4a7c3f',
                'destacado' => true,
                'promocion_titulo' => 'Favorito de cafeterías',
            ],
            [
                'slug' => 'geisha-1k',
                'nombre' => 'Geisha Blend Premium',
                'presentacion' => '1 kg',
                'categoria' => CategoriaProducto::Blends,
                'descripcion' => 'Un blend exclusivo de variedad Geisha con perfil extremadamente floral, notas de jazmín y durazno.',
                'precio' => 22.00,
                'stock_minimo' => 15,
                'acento' => '#b5453a',
            ],
            [
                'slug' => 'mocha-500',
                'nombre' => 'Mocha Espresso Intenso',
                'presentacion' => '500 g',
                'categoria' => CategoriaProducto::EnGrano,
                'descripcion' => 'Tueste oscuro estilo europeo, ideal para café espresso. Cuerpo sumamente denso y notas a cacao amargo.',
                'precio' => 12.80,
                'stock_minimo' => 20,
                'acento' => '#17331c',
            ],
            [
                'slug' => 'descafeinado-500',
                'nombre' => 'Descafeinado de Altura',
                'presentacion' => '500 g',
                'categoria' => CategoriaProducto::Molido,
                'descripcion' => 'Proceso al agua que conserva el cuerpo y el dulzor. Notas a panela y nuez tostada, sin cafeína.',
                'precio' => 13.50,
                'stock_minimo' => 15,
                'acento' => '#7fa95c',
            ],
            [
                'slug' => 'colombia-250',
                'nombre' => 'Origen Colombia Orgánico',
                'presentacion' => '250 g',
                'categoria' => CategoriaProducto::Molido,
                'descripcion' => 'Cultivo orgánico certificado del Huila. Taza limpia con acidez de manzana verde y final a caramelo.',
                'precio' => 15.50,
                'stock_minimo' => 20,
                'acento' => '#d9a441',
                'destacado' => true,
                'promocion_titulo' => 'Semana del origen',
                'descuento' => 15,
                'promocion_inicia_at' => '-2 days',
                'promocion_termina_at' => '+12 days',
            ],
        ];

        foreach ($productos as $producto) {
            $existente = Producto::where('slug', $producto['slug'])->first();

            Producto::updateOrCreate(['slug' => $producto['slug']], [
                ...$producto,
                'promocion_inicia_at' => isset($producto['promocion_inicia_at'])
                    ? Carbon::parse($producto['promocion_inicia_at'])
                    : null,
                'promocion_termina_at' => isset($producto['promocion_termina_at'])
                    ? Carbon::parse($producto['promocion_termina_at'])
                    : null,
                // Al resembrar no se pisan el stock ya sincronizado ni una foto
                // que alguien haya subido desde el catálogo.
                'stock' => $existente?->stock ?? 0,
                'imagen' => $existente?->imagen ?? $this->publicarFoto($producto['slug']),
            ]);
        }
    }

    /**
     * Copia la foto de prueba al disco público y devuelve su ruta relativa.
     */
    private function publicarFoto(string $slug): ?string
    {
        $origen = self::ORIGEN_FOTOS."/{$slug}.png";

        if (! is_file($origen)) {
            return null;
        }

        $destino = self::DESTINO_FOTOS."/{$slug}.png";

        Storage::disk('public')->put($destino, file_get_contents($origen));

        return $destino;
    }
}
