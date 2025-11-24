<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route; // <- asegurarse de importar Route

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Cargar rutas web si existen
        if (file_exists(base_path('routes/web.php'))) {
            require base_path('routes/web.php');
        }

        // Cargar rutas API desde routes/api.php con prefijo /api y middleware 'api'
        if (file_exists(base_path('routes/api.php'))) {
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        }
    }
}
