<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    // =============================
    // 📌 LISTAR PRODUCTOS
    // =============================
    public function index()
    {
        return response()->json([
            'productos' => Producto::with('categoria')->orderBy('id', 'DESC')->get()
        ]);
    }

    // =============================
    // 📌 CREAR PRODUCTO
    // =============================
    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo'       => 'required|unique:productos,codigo',
            'nombre'       => 'required|string',
            'descripcion'  => 'nullable|string',
            'id_categoria' => 'required|exists:categorias,id',
            'precio'       => 'required|numeric|min:0',
            'imagen'       => 'nullable|string',
            'activo'       => 'boolean'
        ]);

        // Manejo de imagen si se sube
        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('productos', 'public');
        }

        $producto = Producto::create($data);

        return response()->json([
            'message' => 'Producto creado correctamente',
            'producto' => $producto
        ]);
    }

    // =============================
    // 📌 MOSTRAR UN PRODUCTO
    // =============================
    public function show($id)
    {
        $producto = Producto::with('categoria')->findOrFail($id);

        return response()->json($producto);
    }

    // =============================
    // 📌 ACTUALIZAR PRODUCTO
    // =============================
    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);

        $data = $request->validate([
            'codigo'       => 'sometimes|unique:productos,codigo,' . $id,
            'nombre'       => 'sometimes|string',
            'descripcion'  => 'nullable|string',
            'id_categoria' => 'sometimes|exists:categorias,id',
            'precio'       => 'sometimes|numeric|min:0',
            'imagen'       => 'nullable|string',
            'activo'       => 'boolean'
        ]);

        // Si hay nueva imagen, eliminar la anterior y subir la nueva
        if ($request->hasFile('imagen')) {
            if ($producto->imagen) {
                Storage::disk('public')->delete($producto->imagen);
            }
            $data['imagen'] = $request->file('imagen')->store('productos', 'public');
        }

        $producto->update($data);

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'producto' => $producto
        ]);
    }

    // =============================
    // 📌 ELIMINAR PRODUCTO
    // =============================
    public function destroy($id)
    {
        $producto = Producto::findOrFail($id);

        // Eliminar imagen si existe
        if ($producto->imagen) {
            Storage::disk('public')->delete($producto->imagen);
        }

        $producto->delete();

        return response()->json([
            'message' => 'Producto eliminado correctamente'
        ]);
    }
}
