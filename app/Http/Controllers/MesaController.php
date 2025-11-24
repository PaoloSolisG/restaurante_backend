<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use Illuminate\Http\Request;

class MesaController extends Controller
{
    /**
     * Listar todas las mesas
     */
    public function index()
    {
        return response()->json(Mesa::all(), 200);
    }

    /**
     * Crear una nueva mesa
     */
    public function store(Request $request)
    {
        $request->validate([
            'numero' => 'required|string|unique:mesas,numero',
            'capacidad' => 'required|integer|min:1',
            'estado' => 'in:libre,ocupada,limpieza,reservada',
            'activo' => 'boolean'
        ]);

        $mesa = Mesa::create([
            'numero' => $request->numero,
            'capacidad' => $request->capacidad,
            'estado' => $request->estado ?? 'libre',
            'activo' => $request->activo ?? true,
        ]);

        return response()->json([
            'message' => 'Mesa creada correctamente',
            'data' => $mesa
        ], 201);
    }

    /**
     * Mostrar una mesa por ID
     */
    public function show($id)
    {
        $mesa = Mesa::find($id);

        if (!$mesa) {
            return response()->json(['message' => 'Mesa no encontrada'], 404);
        }

        return response()->json($mesa, 200);
    }

    /**
     * Actualizar mesa
     */
    public function update(Request $request, $id)
    {
        $mesa = Mesa::find($id);

        if (!$mesa) {
            return response()->json(['message' => 'Mesa no encontrada'], 404);
        }

        $request->validate([
            'numero' => 'string|unique:mesas,numero,' . $mesa->id,
            'capacidad' => 'integer|min:1',
            'estado' => 'in:libre,ocupada,limpieza,reservada',
            'activo' => 'boolean'
        ]);

        $mesa->update($request->all());

        return response()->json([
            'message' => 'Mesa actualizada correctamente',
            'data' => $mesa
        ], 200);
    }

    /**
     * Eliminar mesa
     */
    public function destroy($id)
    {
        $mesa = Mesa::find($id);

        if (!$mesa) {
            return response()->json(['message' => 'Mesa no encontrada'], 404);
        }

        $mesa->delete();

        return response()->json(['message' => 'Mesa eliminada correctamente'], 200);
    }
}
