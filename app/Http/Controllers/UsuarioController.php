<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    // ─── Listar todos ───────────────────────────────────────────────
    // GET /api/usuarios
    // GET /api/usuarios?rol=cocina
    // GET /api/usuarios?rol=admin
    // GET /api/usuarios?search=juan
    public function index(Request $request)
    {
        $query = Usuario::query();

        if ($request->filled('rol')) {
            $query->where('rol', $request->rol);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($b) use ($q) {
                $b->where('nombre',    'like', "%{$q}%")
                  ->orWhere('apellido','like', "%{$q}%")
                  ->orWhere('email',   'like', "%{$q}%");
            });
        }

        $usuarios = $query->orderBy('nombre')->get();

        return response()->json([
            'status' => true,
            'data'   => $usuarios,
        ]);
    }

    // ─── Crear usuario ───────────────────────────────────────────────
    // POST /api/usuarios
    public function store(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'email'    => 'required|email|unique:usuarios,email',
            'password' => 'required|string|min:6',
            'rol'      => 'nullable|in:cliente,cocina,admin',
        ]);

        $usuario = Usuario::create([
            'nombre'   => $request->nombre,
            'apellido' => $request->apellido ?? '',
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'rol'      => $request->rol ?? 'cliente',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Usuario creado correctamente',
            'data'    => $usuario,
        ], 201);
    }

    // ─── Ver uno ─────────────────────────────────────────────────────
    // GET /api/usuarios/{id}
    public function show($id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'status'  => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $usuario,
        ]);
    }

    // ─── Actualizar ──────────────────────────────────────────────────
    // PUT /api/usuarios/{id}
    public function update(Request $request, $id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'status'  => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $request->validate([
            'nombre'   => 'sometimes|required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'email'    => ['nullable', 'email', Rule::unique('usuarios', 'email')->ignore($id)],
            'password' => 'nullable|string|min:6',
            'rol'      => 'nullable|in:cliente,cocina,admin',
        ]);

        $datos = $request->only(['nombre', 'apellido', 'email', 'rol']);

        // Solo hashear password si se envió
        if ($request->filled('password')) {
            $datos['password'] = Hash::make($request->password);
        }

        $usuario->update($datos);

        return response()->json([
            'status'  => true,
            'message' => 'Usuario actualizado',
            'data'    => $usuario->fresh(),
        ]);
    }

    // ─── Cambiar solo el rol ─────────────────────────────────────────
    // PATCH /api/usuarios/{id}/rol
    // Body: { "rol": "admin" }
    public function cambiarRol(Request $request, $id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'status'  => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $request->validate([
            'rol' => 'required|in:cliente,cocina,admin',
        ]);

        $rolAnterior = $usuario->rol;
        $usuario->update(['rol' => $request->rol]);

        return response()->json([
            'status'  => true,
            'message' => "Rol cambiado de '{$rolAnterior}' a '{$request->rol}'",
            'data'    => $usuario->fresh(),
        ]);
    }

    // ─── Eliminar ────────────────────────────────────────────────────
    // DELETE /api/usuarios/{id}
    public function destroy($id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'status'  => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // No puede eliminarse a sí mismo
        if (request()->user() && request()->user()->id == $id) {
            return response()->json([
                'status'  => false,
                'message' => 'No puedes eliminar tu propio usuario',
            ], 403);
        }

        // Revocar tokens Sanctum antes de eliminar
        $usuario->tokens()->delete();
        $usuario->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Usuario eliminado correctamente',
        ]);
    }

    // ─── Perfil del usuario autenticado ─────────────────────────────
    // GET /api/perfil
    public function perfil(Request $request)
    {
        return response()->json([
            'status' => true,
            'data'   => $request->user(),
        ]);
    }

    // ─── Actualizar propio perfil ────────────────────────────────────
    // PUT /api/perfil
    // Para cambiar password enviar: password_actual + password_nuevo + password_nuevo_confirmation
    public function actualizarPerfil(Request $request)
    {
        $usuario = $request->user();

        $request->validate([
            'nombre'                  => 'sometimes|required|string|max:255',
            'apellido'                => 'nullable|string|max:255',
            'email'                   => ['nullable', 'email', Rule::unique('usuarios', 'email')->ignore($usuario->id)],
            'password_actual'         => 'required_with:password_nuevo|string',
            'password_nuevo'          => 'nullable|string|min:6|confirmed',
        ]);

        // Verificar contraseña actual si quiere cambiarla
        if ($request->filled('password_nuevo')) {
            if (!Hash::check($request->password_actual, $usuario->password)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'La contraseña actual no es correcta',
                ], 422);
            }
        }

        $datos = $request->only(['nombre', 'apellido', 'email']);

        if ($request->filled('password_nuevo')) {
            $datos['password'] = Hash::make($request->password_nuevo);
        }

        $usuario->update($datos);

        return response()->json([
            'status'  => true,
            'message' => 'Perfil actualizado correctamente',
            'data'    => $usuario->fresh(),
        ]);
    }
}