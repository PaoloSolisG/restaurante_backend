<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Registro de usuario
    public function register(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'email'    => 'required|email|unique:usuarios,email',
            'password' => 'required|string|min:6',
            'role_id'  => 'nullable|exists:roles,id',
        ]);

        $rolMozo = \App\Models\Role::where('nombre', 'mozo')->value('id');

        $user = Usuario::create([
            'nombre'   => $request->nombre,
            'apellido' => $request->apellido,
            'email'    => $request->email,
            'password' => bcrypt($request->password),
            'role_id'  => $request->role_id ?? $rolMozo,
        ]);

        // Crear token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Respuesta JSON
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'          => $user->load('role'),
        ], 201);
    }


    // LOGIN
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
            'codigo'   => 'nullable|string',
        ]);

        $user = Usuario::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load('role');

        $response = [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ];

        if ($request->filled('codigo')) {
            $mesa = Mesa::where('codigo', $request->codigo)->first();
            $response['mesa'] = $mesa;
        }

        return response()->json($response);
    }


    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Todas las sesiones han sido cerradas'
        ]);
    }


    // ─── Logout solo el token actual ─────────────────────────────────
    // POST /api/logout/current
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Sesión cerrada correctamente',
        ]);
    }

    // ─── Verificar token (útil para el frontend al recargar) ─────────
    // GET /api/me
    public function me(Request $request)
    {
        $user = $request->user()->load('role');
        return response()->json([
            'status' => true,
            'user'   => $user,
        ]);
    }
}
