<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!$request->user()?->tienePermiso('roles')) {
                return response()->json([
                    'status' => false,
                    'message' => 'No tienes permisos para gestionar roles',
                ], 403);
            }
            return $next($request);
        });
    }

    public function index()
    {
        $roles = Role::withCount('usuarios')->orderBy('nombre')->get();
        return response()->json(['status' => true, 'data' => $roles]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'      => 'required|string|max:50|unique:roles,nombre',
            'descripcion' => 'nullable|string|max:200',
            'permisos'    => 'required|array',
        ]);

        $role = Role::create([
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'permisos'    => $request->permisos,
            'editable'    => true,
        ]);

        return response()->json(['status' => true, 'message' => 'Rol creado', 'data' => $role], 201);
    }

    public function show($id)
    {
        $role = Role::withCount('usuarios')->find($id);
        if (!$role) {
            return response()->json(['status' => false, 'message' => 'Rol no encontrado'], 404);
        }
        return response()->json(['status' => true, 'data' => $role]);
    }

    public function update(Request $request, $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['status' => false, 'message' => 'Rol no encontrado'], 404);
        }

        $request->validate([
            'nombre'      => ['sometimes', 'string', 'max:50', Rule::unique('roles', 'nombre')->ignore($id)],
            'descripcion' => 'nullable|string|max:200',
            'permisos'    => 'sometimes|array',
        ]);

        $role->update($request->only(['nombre', 'descripcion', 'permisos']));

        return response()->json(['status' => true, 'message' => 'Rol actualizado', 'data' => $role->fresh()]);
    }

    public function destroy($id)
    {
        $role = Role::withCount('usuarios')->find($id);
        if (!$role) {
            return response()->json(['status' => false, 'message' => 'Rol no encontrado'], 404);
        }

        if (!$role->editable) {
            return response()->json(['status' => false, 'message' => 'Los roles del sistema no se pueden eliminar'], 403);
        }

        if ($role->usuarios_count > 0) {
            return response()->json(['status' => false, 'message' => 'No se puede eliminar: hay usuarios con este rol'], 400);
        }

        $role->delete();
        return response()->json(['status' => true, 'message' => 'Rol eliminado']);
    }
}
