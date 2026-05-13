<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TenantController;

/*
|--------------------------------------------------------------------------
| Central API Routes (no tenant context)
| Base URL: /api
|--------------------------------------------------------------------------
*/

Route::get('/test', function () {
    return response()->json(['message' => 'Central API OK', 'env' => app()->environment()]);
});

// Gestión de tenants (administración SaaS)
// En producción proteger con middleware de super-admin
Route::prefix('tenants')->controller(TenantController::class)->group(function () {
    Route::get('/',           'index');
    Route::post('/',          'store');
    Route::get('/{id}',       'show');
    Route::put('/{id}',       'update');
    Route::delete('/{id}',    'destroy');
    Route::post('/{id}/migrar', 'migrar');
});
