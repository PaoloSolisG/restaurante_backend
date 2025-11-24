<?php

namespace App\Http\Controllers;

use App\Models\Cocinero;
use Illuminate\Http\Request;

class CocineroController extends Controller
{
    // Listar cocineros
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => Cocinero::all()
        ]);
    }

    // Crear cocinero
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string',
            'apellido' => 'required|string',
            'email' => 'nullable|email|unique:cocineros,email',
            'telefono' => 'nullable|string',
        ]);

        $cocinero = Cocinero::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Cocinero creado correctamente',
            'data' => $cocinero
        ]);
    }

    // Mostrar cocinero
    public function show($id)
    {
        $cocinero = Cocinero::find($id);

        if (!$cocinero) {
            return response()->json([
                'status' => false,
                'message' => 'Cocinero no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $cocinero
        ]);
    }

    // Actualizar cocinero
    public function update(Request $request, $id)
    {
        $cocinero = Cocinero::find($id);

        if (!$cocinero) {
            return response()->json([
                'status' => false,
                'message' => 'Cocinero no encontrado'
            ], 404);
        }

        $request->validate([
            'nombre' => 'string',
            'apellido' => 'string',
            'email' => 'nullable|email|unique:cocineros,email,' . $id,
            'telefono' => 'nullable|string',
            'activo' => 'boolean'
        ]);

        $cocinero->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Cocinero actualizado correctamente',
            'data' => $cocinero
        ]);
    }

    // Eliminar cocinero
    public function destroy($id)
    {
        $cocinero = Cocinero::find($id);

        if (!$cocinero) {
            return response()->json([
                'status' => false,
                'message' => 'Cocinero no encontrado'
            ], 404);
        }

        $cocinero->delete();

        return response()->json([
            'status' => true,
            'message' => 'Cocinero eliminado correctamente'
        ]);
    }
}
