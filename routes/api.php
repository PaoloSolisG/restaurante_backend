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

// Resolución de tenant por dominio — usado por el frontend para detectar el tenant del subdominio
Route::get('/tenant-by-domain', function (\Illuminate\Http\Request $request) {
    $domain = $request->query('domain');
    if (!$domain) {
        return response()->json(['error' => 'domain requerido'], 400);
    }
    $row = \Stancl\Tenancy\Database\Models\Domain::where('domain', $domain)->first();
    if (!$row) {
        return response()->json(['error' => 'Dominio no encontrado'], 404);
    }
    return response()->json(['tenant_id' => $row->tenant_id]);
});

// Gestión de tenants (administración SaaS)
Route::prefix('tenants')->controller(TenantController::class)->group(function () {
    Route::get('/',             'index');
    Route::post('/',            'store');
    Route::get('/{id}',         'show');
    Route::put('/{id}',         'update');
    Route::delete('/{id}',      'destroy');
    Route::post('/{id}/migrar', 'migrar');
});
