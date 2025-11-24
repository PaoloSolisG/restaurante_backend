<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Categoria;

class CategoriaController extends Controller
{
    // Listar todas las categorías
    public function index()
    {
        return response()->json(Categoria::all());
    }

    // Crear una nueva categoría
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|unique:categorias,nombre',
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
            'area' => 'required|string' // <-- validar área al crear
        ]);

        $categoria = Categoria::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'activo' => $request->activo ?? true,
            'area' => $request->area
        ]);

        return response()->json($categoria, 201);
    }

    // Mostrar una categoría específica
    public function show($id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        return response()->json($categoria);
    }

    // Actualizar categoría
    public function update(Request $request, $id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        $request->validate([
            'nombre' => 'sometimes|required|string|unique:categorias,nombre,' . $id,
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
            'area' => 'sometimes|required|string' // <-- validar área al actualizar
        ]);

        $categoria->update($request->all());

        return response()->json($categoria);
    }

    // Eliminar categoría
    public function destroy($id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada']);
    }
}
