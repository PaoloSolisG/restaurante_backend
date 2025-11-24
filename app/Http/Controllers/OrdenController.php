<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Mesa;
use App\Models\Orden;
use App\Models\OrdenDetalle;
use App\Models\Producto;
use Illuminate\Http\Request;

class OrdenController extends Controller
{
    /**
     * Listar órdenes
     */
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => Orden::with('mesa', 'cliente', 'detalles.producto')->get()
        ]);
    }

    /**
     * Crear orden con detalles
     */
    public function store(Request $request)
    {
        $request->validate([
            'mesa_id' => 'required|exists:mesas,id',
            'cliente_id' => 'nullable|integer',
            'tipo_consumo' => 'required|in:mesa,llevar,delivery',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|integer',
            'detalles.*.cantidad' => 'required|integer|min:1'
        ]);

        // Validar cliente si se proporciona
        if ($request->cliente_id) {
            $cliente = Cliente::find($request->cliente_id);
            if (!$cliente) {
                return response()->json([
                    'status' => false,
                    'message' => "El cliente con ID {$request->cliente_id} no existe"
                ], 404);
            }
        }

        // Validar mesa y su estado
        $mesa = Mesa::find($request->mesa_id);
        if (!$mesa) {
            return response()->json([
                'status' => false,
                'message' => "La mesa con ID {$request->mesa_id} no existe"
            ], 404);
        }

        if ($mesa->estado === 'ocupada') {
            return response()->json([
                'status' => false,
                'message' => "La mesa {$mesa->numero} está ocupada"
            ], 400);
        }


        // Validación manual de productos
        foreach ($request->detalles as $item) {
            if (!Producto::find($item['producto_id'])) {
                return response()->json([
                    'status' => false,
                    'message' => "El producto con ID {$item['producto_id']} no existe"
                ], 404);
            }
        }

        // Crear la orden
        $orden = Orden::create([
            'mesa_id' => $request->mesa_id,
            'cliente_id' => $request->cliente_id,
            'tipo_consumo' => $request->tipo_consumo,
            'notas' => $request->notas,
            'subtotal' => 0,
            'total' => 0,
        ]);

        $mesa->update([
            'estado' => 'ocupada'
        ]);

        $subtotal = 0;

        foreach ($request->detalles as $item) {
            $producto = Producto::find($item['producto_id']);
            $lineaSubtotal = $producto->precio * $item['cantidad'];
            $subtotal += $lineaSubtotal;

            // Determinar el área según tipo de producto
            $area = $producto->categoria->area ?? 'cocina';


            OrdenDetalle::create([
                'orden_id' => $orden->id,
                'producto_id' => $producto->id,
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $producto->precio,
                'subtotal' => $lineaSubtotal,
                'cocinero_id' => null, // reservado para futuro
                'area' => $area,
                'estado' => 'pendiente'
            ]);
        }


        $orden->update([
            'subtotal' => $subtotal,
            'total' => $subtotal
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Orden creada correctamente',
            'data' => $orden->load('detalles.producto')
        ]);
    }


    /**
     * Mostrar orden
     */
    public function show($id)
    {
        $orden = Orden::with('mesa', 'cliente', 'detalles.producto')->find($id);

        if (!$orden) {
            return response()->json(['status' => false, 'message' => 'Orden no encontrada'], 404);
        }

        return response()->json(['status' => true, 'data' => $orden]);
    }

    /**
     * Cambiar estado
     */
    public function update(Request $request, $id)
    {
        $orden = Orden::find($id);

        if (!$orden) {
            return response()->json(['status' => false, 'message' => 'Orden no encontrada'], 404);
        }

        $request->validate([
            'estado' => 'in:pendiente,en_preparacion,listo,entregado,cerrado,cancelado',
            'notas' => 'nullable|string'
        ]);

        $orden->update($request->only(['estado', 'notas']));

        return response()->json(['status' => true, 'message' => 'Orden actualizada']);
    }

    /**
     * Eliminar orden
     */
    public function destroy($id)
    {
        $orden = Orden::find($id);

        if (!$orden) {
            return response()->json(['status' => false, 'message' => 'Orden no encontrada'], 404);
        }

        $orden->delete();

        return response()->json(['status' => true, 'message' => 'Orden eliminada']);
    }

    public function ordenesPorArea($area)
    {
        $areas_validas = ['cocina', 'barra'];
        if (!in_array($area, $areas_validas)) {
            return response()->json([
                'status' => false,
                'message' => 'Área inválida'
            ], 400);
        }

        $detalles = OrdenDetalle::with(['orden.mesa', 'orden.cliente', 'producto'])
            ->where('area', $area)
            ->where('estado', 'pendiente')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $detalles
        ]);
    }

    public function ordenesPorCliente($cliente_id)
    {
        // Verificar si existe el cliente
        $cliente = \App\Models\Cliente::find($cliente_id);
        if (!$cliente) {
            return response()->json([
                'status' => false,
                'message' => "Cliente con ID {$cliente_id} no existe"
            ], 404);
        }

        // Traer las órdenes del cliente con detalles y productos
        $ordenes = \App\Models\Orden::with('mesa', 'detalles.producto')
            ->where('cliente_id', $cliente_id)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $ordenes
        ]);
    }

    public function ordenesPorMesa($mesa_id)
    {
        // Verificar si existe la mesa
        $mesa = \App\Models\Mesa::find($mesa_id);
        if (!$mesa) {
            return response()->json([
                'status' => false,
                'message' => "Mesa con ID {$mesa_id} no existe"
            ], 404);
        }

        // Traer las órdenes de la mesa con detalles y productos
        $ordenes = \App\Models\Orden::with('cliente', 'detalles.producto')
            ->where('mesa_id', $mesa_id)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $ordenes
        ]);
    }

    public function ordenesPorEstado($estado)
    {
        // Validar que el estado sea uno de los permitidos
        $estadosPermitidos = ['pendiente', 'en_preparacion', 'listo', 'entregado', 'cerrado', 'cancelado'];
        if (!in_array($estado, $estadosPermitidos)) {
            return response()->json([
                'status' => false,
                'message' => "Estado '{$estado}' no es válido. Los válidos son: " . implode(', ', $estadosPermitidos)
            ], 400);
        }

        // Traer las órdenes con detalles y productos según el estado
        $ordenes = \App\Models\Orden::with('mesa', 'cliente', 'detalles.producto')
            ->where('estado', $estado)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $ordenes
        ]);
    }

    public function asignarMozo(Request $request, $id)
    {
        $request->validate([
            'mozo_id' => 'required|exists:mozos,id'
        ]);

        $orden = Orden::find($id);

        if (!$orden) {
            return response()->json([
                'status' => false,
                'message' => 'Orden no encontrada'
            ], 404);
        }

        $orden->mozo_id = $request->mozo_id;
        $orden->save();

        return response()->json([
            'status' => true,
            'message' => "Mozo asignado correctamente",
            'data' => $orden->load('mesa', 'cliente', 'detalles.producto', 'mozo')
        ]);
    }
}
