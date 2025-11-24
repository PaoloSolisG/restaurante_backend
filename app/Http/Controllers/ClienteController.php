<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    /**
     * LISTAR CLIENTES
     */
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => Cliente::orderBy('id', 'desc')->get()
        ]);
    }

    /**
     * CREAR CLIENTE
     */
    public function store(Request $request)
    {
        $request->validate([
            'tipo_identificador' => 'required|in:DNI,RUC',
            'identificador'      => 'required|unique:clientes',
            'nombre'             => 'required|string',
            'apellido'           => 'required|string',
            'email'              => 'nullable|email|unique:clientes,email',
            'telefono'           => 'nullable|string',
            'direccion'          => 'nullable|string',
            'activo'             => 'boolean'
        ]);

        $cliente = Cliente::create($request->all());

        return response()->json([
            'status'  => true,
            'message' => 'Cliente creado correctamente',
            'data'    => $cliente
        ], 201);
    }

    /**
     * MOSTRAR CLIENTE POR ID
     */
    public function show($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json([
                'status' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $cliente
        ]);
    }

    /**
     * ACTUALIZAR CLIENTE
     */
    public function update(Request $request, $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json([
                'status' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $request->validate([
            'tipo_identificador' => 'in:DNI,RUC',
            'identificador'      => 'unique:clientes,identificador,' . $id,
            'email'              => 'nullable|email|unique:clientes,email,' . $id,
        ]);

        $cliente->update($request->all());

        return response()->json([
            'status'  => true,
            'message' => 'Cliente actualizado correctamente',
            'data'    => $cliente
        ]);
    }

    /**
     * ELIMINAR CLIENTE
     */
    public function destroy($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json([
                'status' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $cliente->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Cliente eliminado correctamente'
        ]);
    }
}
