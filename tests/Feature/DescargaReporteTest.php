<?php

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Enums\FormatoReporte;
use App\Enums\PeriodoReporte;
use App\Enums\Rol;
use App\Enums\Seccion;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\User;
use App\Support\Reportes\CatalogoDeReportes;
use App\Support\Reportes\EscritorExcel;
use App\Support\Reportes\TablaReporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class DescargaReporteTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_seccion_del_menu_se_descarga_en_pdf_y_en_excel(): void
    {
        $this->datosDeMuestra();
        $administrador = User::factory()->conRol(Rol::Administrador)->create();

        foreach (Rol::Administrador->secciones() as $seccion) {
            foreach (FormatoReporte::cases() as $formato) {
                $respuesta = $this->actingAs($administrador)->get(route('reportes.descargar', [
                    'seccion' => $seccion->value,
                    'formato' => $formato->value,
                ]));

                $respuesta->assertOk();
                $respuesta->assertHeader('content-type', $formato->tipoMime());
                $this->assertStringContainsString(
                    '.'.$formato->extension().'"',
                    (string) $respuesta->headers->get('content-disposition'),
                );

                $contenido = $respuesta->getContent();

                $formato === FormatoReporte::Pdf
                    ? $this->assertStringStartsWith('%PDF-1.4', $contenido)
                    : $this->assertStringStartsWith("PK\x03\x04", $contenido);
            }
        }
    }

    public function test_el_pdf_cierra_el_documento_con_su_tabla_de_referencias(): void
    {
        $this->datosDeMuestra();

        $contenido = $this->actingAs(User::factory()->conRol(Rol::Administrador)->create())
            ->get(route('reportes.descargar', ['seccion' => Seccion::Reportes->value, 'formato' => FormatoReporte::Pdf->value]))
            ->getContent();

        $this->assertStringEndsWith('%%EOF', $contenido);
        $this->assertStringContainsString('/Type /Catalog', $contenido);
        $this->assertStringContainsString('startxref', $contenido);
        $this->assertGreaterThan(2000, strlen($contenido));
    }

    public function test_el_libro_de_excel_trae_una_hoja_por_tabla_del_reporte(): void
    {
        $this->datosDeMuestra();
        $administrador = User::factory()->conRol(Rol::Administrador)->create();

        $reporte = (new CatalogoDeReportes($administrador))->para(Seccion::Almacen);
        $archivo = tempnam(sys_get_temp_dir(), 'reporte-');
        file_put_contents($archivo, (new EscritorExcel)->generar($reporte));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivo) === true);

        // Una hoja de resumen más una por cada tabla del reporte.
        foreach (range(1, count($reporte->tablas) + 1) as $numero) {
            $this->assertNotFalse($zip->locateName('xl/worksheets/sheet'.$numero.'.xml'));
        }

        $libro = (string) $zip->getFromName('xl/workbook.xml');
        $zip->close();
        unlink($archivo);

        $this->assertStringContainsString('name="Resumen"', $libro);
        $this->assertStringContainsString('name="Reposición sugerida"', $libro);
    }

    public function test_el_reporte_gerencial_lleva_los_indicadores_y_los_mas_vendidos(): void
    {
        $pedido = Pedido::factory()->enEstado(EstadoPedido::Entregado)->create(['total' => 120.00]);
        $estrella = Producto::factory()->create(['nombre' => 'Geisha Blend Premium']);
        PedidoLinea::factory()->for($pedido)->deProducto($estrella, 12)->create();

        $reporte = (new CatalogoDeReportes(
            User::factory()->conRol(Rol::DireccionGeneral)->create(),
            ['periodo' => PeriodoReporte::Mes->value],
        ))->para(Seccion::Reportes);

        $this->assertSame('Reporte gerencial', $reporte->titulo);
        $this->assertSame('$120.00', $reporte->indicadores[0]->valor);

        $masVendidos = $this->tabla($reporte->tablas, 'Productos más vendidos');

        $this->assertSame('Geisha Blend Premium', $masVendidos->filas[0][0]);
        $this->assertSame(12, $masVendidos->filas[0][2]);

        $ventas = $this->tabla($reporte->tablas, 'Ventas por periodo');

        $this->assertCount(PeriodoReporte::Mes->tramos(), $ventas->filas);
    }

    public function test_el_cliente_solo_descarga_los_pedidos_registrados_a_su_nombre(): void
    {
        $cliente = User::factory()->conRol(Rol::Cliente)->create();

        Pedido::factory()->for($cliente, 'usuario')->create(['codigo' => 'PED-MIO']);
        Pedido::factory()->create(['codigo' => 'PED-AJENO']);

        $tabla = $this->tabla(
            (new CatalogoDeReportes($cliente))->para(Seccion::Historial)->tablas,
            'Pedidos registrados',
        );

        $this->assertSame(['PED-MIO'], array_column($tabla->filas, 0));
    }

    public function test_un_rol_no_descarga_el_reporte_de_una_seccion_que_no_ve(): void
    {
        $cliente = User::factory()->conRol(Rol::Cliente)->create();

        foreach ([Seccion::Reportes, Seccion::Almacen, Seccion::Usuarios] as $seccion) {
            $this->actingAs($cliente)
                ->get(route('reportes.descargar', ['seccion' => $seccion->value, 'formato' => 'pdf']))
                ->assertForbidden();
        }

        $this->actingAs($cliente)
            ->get(route('reportes.descargar', ['seccion' => Seccion::Catalogo->value, 'formato' => 'pdf']))
            ->assertOk();
    }

    public function test_una_seccion_o_un_formato_desconocidos_no_existen(): void
    {
        $administrador = User::factory()->conRol(Rol::Administrador)->create();

        $this->actingAs($administrador)
            ->get(route('reportes.descargar', ['seccion' => 'inventado', 'formato' => 'pdf']))
            ->assertNotFound();

        $this->actingAs($administrador)
            ->get(route('reportes.descargar', ['seccion' => Seccion::Reportes->value, 'formato' => 'word']))
            ->assertNotFound();
    }

    public function test_una_visita_sin_sesion_va_al_ingreso(): void
    {
        $this->get(route('reportes.descargar', ['seccion' => Seccion::Reportes->value, 'formato' => 'pdf']))
            ->assertRedirect(route('login'));
    }

    /**
     * @param  list<TablaReporte>  $tablas
     */
    private function tabla(array $tablas, string $titulo): TablaReporte
    {
        $tabla = collect($tablas)->firstWhere('titulo', $titulo);

        $this->assertInstanceOf(TablaReporte::class, $tabla, "El reporte no trae la tabla «{$titulo}».");

        return $tabla;
    }

    /** Catálogo, almacén y pedidos con datos suficientes para llenar cada bloque. */
    private function datosDeMuestra(): void
    {
        $producto = Producto::factory()->conStock(40)->enPromocion()->create(['nombre' => 'Geisha Blend Premium']);
        Producto::factory()->agotado()->create(['nombre' => 'Mocha Espresso']);

        Lote::factory()->for($producto)->porVencer()->conCantidad(30)->create();
        Lote::factory()->for($producto)->vencido()->conCantidad(10)->create();
        Lote::factory()->for($producto)->rechazado()->conCantidad(5)->create();
        $producto->sincronizarStock();

        $pedido = Pedido::factory()->enEstado(EstadoPedido::Entregado)->create();
        PedidoLinea::factory()->for($pedido)->deProducto($producto, 6)->create();

        Pedido::factory()->enEstado(EstadoPedido::Pendiente)->create();
    }
}
