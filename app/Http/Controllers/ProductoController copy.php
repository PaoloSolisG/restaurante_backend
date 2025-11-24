<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    // Listar
    public function index()
    {
        return response()->json([
            'productos' => Producto::with('categoria')->get()
        ]);
    }

    // Crear
    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => 'required|unique:productos',
            'nombre' => 'required',
            'descripcion' => 'nullable',
            'id_categoria' => 'required|exists:categorias,id',
            'precio' => 'required|numeric|min:0',
            'imagen' => 'nullable',
            'activo' => 'boolean'
        ]);

        $producto = Producto::create($data);

        return response()->json([
            'message' => 'Producto creado correctamente',
            'producto' => $producto
        ]);
    }

    // Mostrar producto
    public function show($id)
    {
        return Producto::with('categoria')->findOrFail($id);
    }

    // Actualizar
    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);

        $data = $request->validate([
            'codigo' => 'sometimes|unique:productos,codigo,' . $id,
            'nombre' => 'sometimes',
            'descripcion' => 'nullable',
            'id_categoria' => 'sometimes|exists:categorias,id',
            'precio' => 'sometimes|numeric|min:0',
            'imagen' => 'nullable',
            'activo' => 'boolean'
        ]);

        $producto->update($data);

        return response()->json([
            'message' => 'Producto actualizado',
            'producto' => $producto
        ]);
    }

    // Eliminar
    public function destroy($id)
    {
        Producto::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Producto eliminado'
        ]);
    }
}
