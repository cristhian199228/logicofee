<?php

use App\Http\Controllers\AlmacenController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AvancePedidoController;
use App\Http\Controllers\BajaLoteController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ControlCalidadController;
use App\Http\Controllers\EstadoUsuarioController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\PagoPedidoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProduccionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProductoFotoController;
use App\Http\Controllers\PromocionController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

// Cada rol entra por la primera sección de su menú.
Route::get('/', function () {
    $usuario = auth()->user();

    return $usuario === null
        ? redirect()->route('login')
        : redirect()->route($usuario->seccionInicial()->ruta());
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware(['auth', 'cuenta.activa'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('seccion:catalogo')->group(function () {
        Route::get('catalogo', [CatalogoController::class, 'index'])->name('catalogo.index');
    });

    Route::middleware('seccion:pedido')->group(function () {
        Route::get('pedido', [PedidoController::class, 'create'])->name('pedidos.create');
        Route::post('pedidos', [PedidoController::class, 'store'])->name('pedidos.store');

        Route::post('carrito', [CarritoController::class, 'store'])->name('carrito.store');
        Route::patch('carrito/{producto}', [CarritoController::class, 'update'])->name('carrito.update');
        Route::delete('carrito/{producto}', [CarritoController::class, 'destroy'])->name('carrito.destroy');
    });

    Route::middleware('seccion:historial')->group(function () {
        Route::get('pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
    });

    Route::middleware('seccion:seguimiento')->group(function () {
        Route::get('seguimiento', [SeguimientoController::class, 'index'])->name('seguimiento.index');
    });

    Route::middleware('seccion:lotes')->group(function () {
        Route::get('lotes', [LoteController::class, 'index'])->name('lotes.index');
    });

    Route::middleware('seccion:calidad')->group(function () {
        Route::get('calidad', [ControlCalidadController::class, 'index'])->name('calidad.index');
    });

    Route::middleware('seccion:promociones')->group(function () {
        Route::get('promociones', [PromocionController::class, 'index'])->name('promociones.index');
    });

    Route::middleware('seccion:reportes')->group(function () {
        Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
    });

    Route::middleware('seccion:usuarios')->group(function () {
        Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    });

    // Paneles por área empresarial: comercial, almacén y producción.
    Route::middleware('seccion:ventas')->group(function () {
        Route::get('ventas', [VentaController::class, 'index'])->name('ventas.index');
    });

    Route::middleware('seccion:almacen')->group(function () {
        Route::get('almacen', [AlmacenController::class, 'index'])->name('almacen.index');
    });

    Route::middleware('seccion:produccion')->group(function () {
        Route::get('produccion', [ProduccionController::class, 'index'])->name('produccion.index');
    });

    Route::middleware('seccion:productos')->group(function () {
        Route::get('productos', [ProductoController::class, 'index'])->name('productos.index');
    });

    // Almacén: entradas de lote y bajas por merma (logística y producción).
    Route::middleware('can:gestionar-inventario')->group(function () {
        Route::post('lotes', [LoteController::class, 'store'])->name('lotes.store');
        Route::post('lotes/{lote}/baja', [BajaLoteController::class, 'store'])->name('lotes.baja.store');
    });

    // Despacho de pedidos: administrador, proveedor y logística.
    Route::middleware('can:despachar-pedidos')->group(function () {
        Route::post('pedidos/{pedido}/avance', [AvancePedidoController::class, 'store'])->name('pedidos.avance.store');
    });

    // Cierre del cobro: además de despacho, también ventas.
    Route::middleware('can:registrar-cobros')->group(function () {
        Route::patch('pedidos/{pedido}/pago', [PagoPedidoController::class, 'update'])->name('pedidos.pago.update');
    });

    // Control de calidad de los lotes: administrador, proveedor y producción.
    Route::middleware('can:controlar-calidad')->group(function () {
        Route::post('lotes/{lote}/calidad', [ControlCalidadController::class, 'store'])->name('calidad.store');
    });

    // Catálogo y fotos: administrador, proveedor y marketing.
    Route::middleware('can:editar-catalogo')->group(function () {
        Route::post('productos', [ProductoController::class, 'store'])->name('productos.store');
        Route::patch('productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
        Route::delete('productos/{producto}', [ProductoController::class, 'destroy'])->name('productos.destroy');

        Route::post('productos/{producto}/foto', [ProductoFotoController::class, 'update'])->name('productos.foto.update');
        Route::delete('productos/{producto}/foto', [ProductoFotoController::class, 'destroy'])->name('productos.foto.destroy');
    });

    // Promociones: administrador y marketing.
    Route::middleware('can:gestionar-promociones')->group(function () {
        Route::patch('productos/{producto}/promocion', [PromocionController::class, 'update'])->name('promociones.update');
    });

    Route::middleware('can:gestionar-usuarios')->group(function () {
        Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::patch('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::patch('usuarios/{usuario}/estado', [EstadoUsuarioController::class, 'update'])->name('usuarios.estado.update');
    });
});
