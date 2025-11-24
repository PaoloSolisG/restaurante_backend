<?php

namespace App\Http\Controllers;

use App\Models\Mozo;
use Illuminate\Http\Request;

class MozoController extends Controller
{
    // Listar todos
    public function index()
    {
        $mozos = Mozo::all();
        return response()->json(['status' => true, 'data' => $mozos]);
    }

    // Crear mozo
    public function store(Request $request)
    {
        $request->validate([
            'identificacion' => 'required|unique:mozos',
            'nombre' => 'required',
            'apellido' => 'required',
            'email' => 'nullable|email|unique:mozos',
            'telefono' => 'nullable',
            'direccion' => 'nullable',
            'activo' => 'boolean'
        ]);

        $mozo = Mozo::create($request->all());

        return response()->json(['status' => true, 'data' => $mozo]);
    }

    // Ver mozo
    public function show($id)
    {
        $mozo = Mozo::find($id);
        if (!$mozo) {
            return response()->json(['status' => false, 'message' => 'Mozo no encontrado'], 404);
        }
        return response()->json(['status' => true, 'data' => $mozo]);
    }

    // Actualizar mozo
    public function update(Request $request, $id)
    {
        $mozo = Mozo::find($id);
        if (!$mozo) {
            return response()->json(['status' => false, 'message' => 'Mozo no encontrado'], 404);
        }

        $request->validate([
            'identificacion' => 'unique:mozos,identificacion,' . $id,
            'email' => 'nullable|email|unique:mozos,email,' . $id,
            'nombre' => 'sometimes|required',
            'apellido' => 'sometimes|required',
            'telefono' => 'nullable',
            'direccion' => 'nullable',
            'activo' => 'boolean'
        ]);

        $mozo->update($request->all());
        return response()->json(['status' => true, 'data' => $mozo]);
    }

    // Eliminar mozo
    public function destroy($id)
    {
        $mozo = Mozo::find($id);
        if (!$mozo) {
            return response()->json(['status' => false, 'message' => 'Mozo no encontrado'], 404);
        }
        $mozo->delete();
        return response()->json(['status' => true, 'message' => 'Mozo eliminado']);
    }
}
