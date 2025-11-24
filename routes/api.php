<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CocineroController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\MozoController;
use App\Http\Controllers\OrdenController;
use App\Http\Controllers\ProductoController;

/*
|--------------------------------------------------------------------------
| Rutas de Prueba
|--------------------------------------------------------------------------
| Útil para verificar que la API responde correctamente.
*/

Route::get('/test', function () {
    return response()->json(['message' => 'API funcionando correctamente']);
});

/*
|--------------------------------------------------------------------------
| Rutas Públicas (No requieren autenticación)
|--------------------------------------------------------------------------
| Registro y Login de usuarios.
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Rutas Protegidas (Requieren token Sanctum)
|--------------------------------------------------------------------------
| Para acceder, en Postman debes enviar:
| Authorization: Bearer TU_TOKEN
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | CRUD Categorías
    |--------------------------------------------------------------------------
    */
    Route::controller(CategoriaController::class)->group(function () {
        Route::get('/categorias', 'index');
        Route::post('/categorias', 'store');
        Route::get('/categorias/{id}', 'show');
        Route::put('/categorias/{id}', 'update');
        Route::delete('/categorias/{id}', 'destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | CRUD Productos
    |--------------------------------------------------------------------------
    */
    Route::controller(ProductoController::class)->group(function () {
        Route::get('/productos', 'index');
        Route::post('/productos', 'store');
        Route::get('/productos/{id}', 'show');
        Route::put('/productos/{id}', 'update');
        Route::delete('/productos/{id}', 'destroy');
    });


    /*
    |--------------------------------------------------------------------------
    | CRUD Mesas
    |--------------------------------------------------------------------------
    */
    Route::controller(MesaController::class)->group(function () {
        Route::get('/mesas', 'index');
        Route::post('/mesas', 'store');
        Route::get('/mesas/{id}', 'show');
        Route::put('/mesas/{id}', 'update');
        Route::delete('/mesas/{id}', 'destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | CRUD Clientes
    |--------------------------------------------------------------------------
    */
    Route::controller(ClienteController::class)->group(function () {
        Route::get('/clientes', 'index');
        Route::post('/clientes', 'store');
        Route::get('/clientes/{id}', 'show');
        Route::put('/clientes/{id}', 'update');
        Route::delete('/clientes/{id}', 'destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Ordenes
    |--------------------------------------------------------------------------
    */
    Route::controller(OrdenController::class)->group(function () {
        Route::get('/ordenes', 'index');
        Route::post('/ordenes', 'store');
        Route::get('/ordenes/{id}', 'show');
        Route::put('/ordenes/{id}', 'update');
        Route::delete('/ordenes/{id}', 'destroy');

        Route::get('/ordenes/area/{area}', [OrdenController::class, 'ordenesPorArea']);
        Route::get('/ordenes/cliente/{cliente_id}', [OrdenController::class, 'ordenesPorCliente']);
        Route::get('/ordenes/mesa/{mesa_id}', [OrdenController::class, 'ordenesPorMesa']);
        Route::get('/ordenes/estado/{estado}', [OrdenController::class, 'ordenesPorEstado']);

        Route::put('/ordenes/{id}/asignar-mozo', [OrdenController::class, 'asignarMozo']);

    });

    /*
    |--------------------------------------------------------------------------
    | CRUD Cocineros
    |--------------------------------------------------------------------------
    */
    Route::controller(CocineroController::class)->group(function () {
        Route::get('/cocineros', 'index');
        Route::post('/cocineros', 'store');
        Route::get('/cocineros/{id}', 'show');
        Route::put('/cocineros/{id}', 'update');
        Route::delete('/cocineros/{id}', 'destroy');
    });


    /*
    |--------------------------------------------------------------------------
    | CRUD Mozos
    |--------------------------------------------------------------------------
    */
    Route::controller(MozoController::class)->group(function () {
        Route::get('/mozos', 'index');
        Route::post('/mozos', 'store');
        Route::get('/mozos/{id}', 'show');
        Route::put('/mozos/{id}', 'update');
        Route::delete('/mozos/{id}', 'destroy');
    });
});
