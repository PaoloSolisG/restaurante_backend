<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * @param  string  $permiso  Permiso requerido (ej: 'usuarios', 'caja', 'roles')
     */
    public function handle(Request $request, Closure $next, string $permiso)
    {
        $user = $request->user();

        if (!$user || !$user->tienePermiso($permiso)) {
            return response()->json([
                'status'  => false,
                'message' => 'No tienes permisos para acceder a este recurso',
            ], 403);
        }

        return $next($request);
    }
}
