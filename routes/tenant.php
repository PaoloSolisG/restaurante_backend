<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\InitializeTenancyOptional;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CocineroController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\MozoController;
use App\Http\Controllers\OrdenController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\PublicCartaController;
use App\Http\Controllers\MozoDashboardController;
use App\Http\Controllers\ReporteController;

Route::middleware(['api', InitializeTenancyOptional::class])->group(function () {

    // ── Carta digital pública (sin auth — acceso por QR) ──────
    Route::prefix('api/public')->group(function () {
        Route::get('/carta/{codigo}',                        [PublicCartaController::class, 'carta']);
        Route::post('/carta/{codigo}/pedido',                [PublicCartaController::class, 'pedido']);
        Route::get('/carta/{codigo}/estado',                 [PublicCartaController::class, 'estado']);
        Route::delete('/carta/{codigo}/detalle/{detalleId}', [PublicCartaController::class, 'cancelarDetalle']);
    });

    Route::get('/api/test-tenant', function () {
        return response()->json(['tenant' => tenant('id'), 'db' => config('database.connections.tenant.database')]);
    });

    // ── Autenticación (pública dentro del tenant) ──────────────
    Route::post('/api/register', [AuthController::class, 'register']);
    Route::post('/api/login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/api/logout',         [AuthController::class, 'logoutAll']);
        Route::post('/api/logout/current', [AuthController::class, 'logout']);
        Route::get('/api/me',              [AuthController::class, 'me']);

        // Consultas externas
        Route::post('/api/consulta/dni', [ConsultaController::class, 'consultarDNI']);
        Route::post('/api/consulta/ruc', [ConsultaController::class, 'consultarRUC']);

        // Configuración del tenant (solo admin)
        Route::get('/api/configuracion',  [ConfiguracionController::class, 'show']);
        Route::put('/api/configuracion',  [ConfiguracionController::class, 'update']);

        // Perfil
        Route::get('/api/perfil', [UsuarioController::class, 'perfil']);
        Route::put('/api/perfil', [UsuarioController::class, 'actualizarPerfil']);

        // ── Usuarios ───────────────────────────────────────────
        Route::controller(UsuarioController::class)->prefix('api/usuarios')->group(function () {
            Route::get('/',               'index');
            Route::post('/',              'store');
            Route::get('/{id}',           'show');
            Route::put('/{id}',           'update');
            Route::patch('/{id}/rol',     'cambiarRol');
            Route::delete('/{id}',        'destroy');
        });

        // ── Roles ──────────────────────────────────────────────
        Route::controller(RoleController::class)->prefix('api/roles')->group(function () {
            Route::get('/',       'index');
            Route::post('/',      'store');
            Route::get('/{id}',   'show');
            Route::put('/{id}',   'update');
            Route::delete('/{id}','destroy');
        });

        // ── Productos ──────────────────────────────────────────
        Route::controller(ProductoController::class)->prefix('api/productos')->group(function () {
            Route::get('/',                        'index');
            Route::post('/',                       'store');
            Route::get('/categoria/{id_categoria}','porCategoria');
            Route::get('/{id}',                    'show');
            Route::put('/{id}',                    'update');
            Route::delete('/{id}',                 'destroy');
        });

        // ── Categorías ─────────────────────────────────────────
        Route::controller(CategoriaController::class)->prefix('api/categorias')->group(function () {
            Route::get('/',       'index');
            Route::post('/',      'store');
            Route::get('/{id}',   'show');
            Route::put('/{id}',   'update');
            Route::delete('/{id}','destroy');
        });

        // ── Mesas ──────────────────────────────────────────────
        Route::controller(MesaController::class)->prefix('api/mesas')->group(function () {
            Route::get('/',       'index');
            Route::post('/',      'store');
            Route::get('/{id}',   'show');
            Route::put('/{id}',   'update');
            Route::delete('/{id}','destroy');
        });

        // ── Clientes ───────────────────────────────────────────
        Route::controller(ClienteController::class)->prefix('api/clientes')->group(function () {
            Route::get('/',       'index');
            Route::post('/',      'store');
            Route::get('/{id}',   'show');
            Route::put('/{id}',   'update');
            Route::delete('/{id}','destroy');
        });

        // ── Órdenes ────────────────────────────────────────────
        Route::controller(OrdenController::class)->prefix('api/ordenes')->group(function () {
            Route::get('/',                        'index');
            Route::post('/',                       'store');
            Route::get('/area/{area}',             'ordenesPorArea');
            Route::get('/cliente/{cliente_id}',    'ordenesPorCliente');
            Route::get('/mesa/{mesa_id}',          'ordenesPorMesa');
            Route::get('/estado/{estado}',         'ordenesPorEstado');
            Route::get('/{id}',                    'show');
            Route::put('/{id}',                    'update');
            Route::delete('/{id}',                 'destroy');
            Route::post('/{id}/detalles',          'agregarDetalles');
            Route::put('/{id}/asignar-mozo',       'asignarMozo');
            Route::post('/{id}/cancelar',          'cancelar');
        });

        // ── Cocineros ──────────────────────────────────────────
        Route::controller(CocineroController::class)->prefix('api/cocineros')->group(function () {
            Route::get('/',       'index');
            Route::post('/',      'store');
            Route::get('/{id}',   'show');
            Route::put('/{id}',   'update');
            Route::delete('/{id}','destroy');
        });

        // ── Mozos ──────────────────────────────────────────────
        Route::controller(MozoController::class)->prefix('api/mozos')->group(function () {
            Route::get('/',       'index');
            Route::post('/',      'store');
            Route::get('/{id}',   'show');
            Route::put('/{id}',   'update');
            Route::delete('/{id}','destroy');
        });

        // ── Caja ───────────────────────────────────────────────
        Route::controller(CajaController::class)->prefix('api/caja')->group(function () {
            Route::get('/estado',      'estado');
            Route::post('/abrir',      'abrir');
            Route::post('/cerrar',     'cerrar');
            Route::post('/movimiento', 'movimiento');
            Route::get('/historial',   'historial');
            Route::get('/{id}',        'show');
        });

        // ── Ventas ─────────────────────────────────────────────
        Route::controller(VentaController::class)->prefix('api/ventas')->group(function () {
            Route::get('/',                  'index');
            Route::post('/',                 'store');
            Route::get('/{id}',              'show');
            Route::post('/{id}/anular',      'anular');
            Route::post('/{id}/nota-credito','notaCredito');

            // Facturación electrónica (anidado en ventas)
            Route::get('/{id}/comprobante/pdf',               [FacturacionController::class, 'pdf']);
            Route::get('/{id}/comprobante/xml',               [FacturacionController::class, 'xml']);
            Route::get('/{id}/comprobante/cdr',               [FacturacionController::class, 'cdr']);
            Route::post('/{id}/comprobante/estado',           [FacturacionController::class, 'consultarEstado']);
            Route::post('/{id}/comprobante/reenviar',         [FacturacionController::class, 'reenviar']);
            Route::post('/{id}/comprobante/emitir',           [FacturacionController::class, 'emitir']);
            Route::post('/{id}/comprobante/dar-baja',         [FacturacionController::class, 'darBaja']);
            Route::post('/{id}/comprobante/consultar-ticket', [FacturacionController::class, 'consultarTicketBaja']);
            Route::post('/{id}/comprobante/vincular',         [FacturacionController::class, 'vincular']);
        });

        Route::get('/api/facturacion/status', [FacturacionController::class, 'status']);

        // ── Mozo Dashboard ─────────────────────────────────────
        Route::get('/api/mozo/mis-ordenes',            [MozoDashboardController::class, 'misOrdenes']);
        Route::post('/api/ordenes/{id}/asignarme',     [MozoDashboardController::class, 'asignarme']);
        Route::post('/api/ordenes/{id}/liberar',       [MozoDashboardController::class, 'liberar']);

        // ── Reportes ───────────────────────────────────────────
        Route::prefix('api/reportes')->controller(ReporteController::class)->group(function () {
            Route::get('/resumen',              'resumen');
            Route::get('/descargar/{tipo}',     'descargar');
        });

        // ── Auditoría ──────────────────────────────────────────
        Route::get('/api/audit-logs', [AuditLogController::class, 'index']);
    });
});
