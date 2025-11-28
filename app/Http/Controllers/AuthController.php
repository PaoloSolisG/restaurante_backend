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
        // Validar datos
        $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'email' => 'required|email|unique:usuarios,email',
            'password' => 'required|string|min:6',
            'rol' => 'nullable|in:cliente,cocina,admin'
        ]);

        // Crear usuario
        $user = Usuario::create([
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'rol' => $request->rol ?? 'cliente'
        ]);

        // Crear token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Respuesta JSON
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }


    // LOGIN
    public function login(Request $request)
    {
        // Validación básica
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'codigo' => 'nullable|string' // este es opcional
        ]);

        // Buscar usuario
        $user = Usuario::where('email', $request->email)->first();

        // Verificar credenciales
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        // Crear token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Preparar respuesta base
        $response = [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ];

        // ✅ Si envían codigo, buscar mesa
        if ($request->filled('codigo')) {
            $mesa = Mesa::where('codigo', $request->codigo)->first();

            if ($mesa) {
                $response['mesa'] = $mesa;
            } else {
                // opcional: devolver mensaje si el código no existe
                $response['mesa'] = null;
            }
        }

        return response()->json($response);
    }
}
