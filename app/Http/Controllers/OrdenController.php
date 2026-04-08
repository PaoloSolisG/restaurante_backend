<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Mesa;
use App\Models\Orden;
use App\Models\OrdenDetalle;
use App\Models\Producto;
use Illuminate\Http\Request;
use App\Events\OrdenCreada;
use Illuminate\Support\Carbon;

class OrdenController extends Controller
{
    /**
     * GET /ordenes
     * Filtros opcionales: ?mesa_id=X  ?estado=Y
     */
    public function index(Request $request)
    {
        $query = Orden::with(['mesa', 'cliente', 'mozo', 'detalles.producto']);

        // Filtro por Rango de Fechas (Solo si se envían)
        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $inicio = Carbon::parse($request->fecha_inicio)->startOfDay();
            $fin = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('created_at', [$inicio, $fin]);
        }
        // Quitamos el "else" para que no restrinja a "solo hoy" por defecto

        // Mantener otros filtros
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $ordenes = $query->orderByDesc('created_at')->get();

        return response()->json([
            'status' => true,
            'data'   => $ordenes,
            'total_periodo' => $ordenes->sum('total')
        ]);
    }

    /**
     * POST /ordenes
     */
    public function store(Request $request)
    {
        $request->validate([
            'mesa_id'                => 'required|exists:mesas,id',
            'cliente_id'             => 'nullable|integer',
            'tipo_consumo'           => 'required|in:mesa,llevar,delivery,para_llevar',
            'detalles'               => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|integer',
            'detalles.*.cantidad'    => 'required|integer|min:1',
        ]);

        if ($request->cliente_id && !Cliente::find($request->cliente_id)) {
            return response()->json(['status' => false, 'message' => "Cliente con ID {$request->cliente_id} no existe"], 404);
        }

        $mesa = Mesa::find($request->mesa_id);
        if (!$mesa) {
            return response()->json(['status' => false, 'message' => "Mesa con ID {$request->mesa_id} no existe"], 404);
        }
        if ($mesa->estado === 'ocupada') {
            return response()->json(['status' => false, 'message' => "La mesa {$mesa->numero} ya está ocupada"], 400);
        }

        foreach ($request->detalles as $item) {
            if (!Producto::find($item['producto_id'])) {
                return response()->json(['status' => false, 'message' => "Producto con ID {$item['producto_id']} no existe"], 404);
            }
        }

        $orden = Orden::create([
            'mesa_id'      => $request->mesa_id,
            'cliente_id'   => $request->cliente_id,
            'mozo_id'      => $request->mozo_id ?? null,
            'tipo_consumo' => $request->tipo_consumo,
            'notas'        => $request->notas,
            'subtotal'     => 0,
            'total'        => 0,
        ]);

        $mesa->update(['estado' => 'ocupada']);

        $subtotal = 0;
        foreach ($request->detalles as $item) {
            $producto      = Producto::find($item['producto_id']);
            $linea         = $producto->precio * $item['cantidad'];
            $subtotal     += $linea;
            $area          = $producto->categoria->area ?? 'cocina';

            OrdenDetalle::create([
                'orden_id'        => $orden->id,
                'producto_id'     => $producto->id,
                'cantidad'        => $item['cantidad'],
                'precio_unitario' => $producto->precio,
                'subtotal'        => $linea,
                'cocinero_id'     => null,
                'area'            => $area,
                'estado'          => 'pendiente',
            ]);
        }

        $orden->update(['subtotal' => $subtotal, 'total' => $subtotal]);
        event(new OrdenCreada($orden->load('mesa', 'cliente', 'detalles.producto')));

        return response()->json([
            'status'  => true,
            'message' => 'Orden creada correctamente',
            'data'    => $orden->load('mesa', 'cliente', 'detalles.producto'),
        ], 201);
    }

    /**
     * GET /ordenes/:id
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
     * PUT /ordenes/:id
     * Actualiza estado, notas, mozo_id
     */
    public function update(Request $request, $id)
    {
        $orden = Orden::find($id);
        if (!$orden) {
            return response()->json(['status' => false, 'message' => 'Orden no encontrada'], 404);
        }

        $request->validate([
            'estado'  => 'nullable|in:pendiente,en_preparacion,listo,entregado,cerrado,cancelado',
            'notas'   => 'nullable|string',
            'mozo_id' => 'nullable|exists:mozos,id',
        ]);

        $orden->update($request->only(['estado', 'notas', 'mozo_id']));

        // Liberar la mesa cuando la orden se cierra o cancela
        if (in_array($request->estado, ['cerrado', 'cancelado']) && $orden->mesa) {
            $orden->mesa->update(['estado' => 'libre']);
        }

        $orden->load('mesa', 'cliente', 'detalles.producto');

        // 5. 🔥 DISPARAR EL EVENTO (Aquí estaba el fallo)
        event(new OrdenCreada($orden));

        return response()->json([
            'status'  => true,
            'message' => 'Orden actualizada',
            'data'    => $orden->fresh('mesa', 'cliente', 'detalles.producto'),
        ]);
    }

    /**
     * DELETE /ordenes/:id
     * Elimina orden y libera la mesa
     */
    public function destroy($id)
    {
        $orden = Orden::find($id);
        if (!$orden) {
            return response()->json(['status' => false, 'message' => 'Orden no encontrada'], 404);
        }
        if ($orden->mesa) {
            $orden->mesa->update(['estado' => 'libre']);
        }
        $orden->delete();
        return response()->json(['status' => true, 'message' => 'Orden eliminada']);
    }

    // ──────────────────────────────────────────────────────────
    // CERRAR ORDEN (PRE CUENTA → FACTURAR)
    // POST /ordenes/:id/cerrar
    //
    // Flujo:
    //   pendiente → en_preparacion → listo → [cerrado]
    //
    // Este endpoint marca la orden como 'cerrado' y libera la mesa.
    // Cuando implementes facturación electrónica, insertas el paso
    // de generar el comprobante ANTES de llamar a este endpoint.
    // ──────────────────────────────────────────────────────────
    public function cerrar(Request $request, $id)
    {
        $orden = Orden::with('mesa', 'cliente', 'detalles.producto')->find($id);

        if (!$orden) {
            return response()->json(['status' => false, 'message' => 'Orden no encontrada'], 404);
        }

        if (in_array($orden->estado, ['cerrado', 'cancelado'])) {
            return response()->json(['status' => false, 'message' => "La orden ya está {$orden->estado}"], 400);
        }

        // Usamos una transacción (como mencionaste que estás testeando transacciones)
        return \Illuminate\Support\Facades\DB::transaction(function () use ($orden) {

            $subtotalReal = $orden->detalles->sum('subtotal');

            // 1. Actualizar orden
            $orden->update([
                'estado'   => 'cerrado',
                'subtotal' => $subtotalReal,
                'total'    => $subtotalReal,
            ]);

            // 2. Liberar la mesa
            if ($orden->mesa) {
                $orden->mesa->update(['estado' => 'libre']);
            }

            // 3. RECARGAR RELACIONES (CRUCIAL)
            // Cargamos la mesa para que el objeto lleve el estado "libre" al frontend
            $orden->load('mesa', 'cliente', 'detalles.producto');

            // 4. DISPARAR EVENTO REVERB / ECHO
            // El frontend recibirá este evento y verá que la mesa asociada ahora es 'libre'
            event(new \App\Events\OrdenCreada($orden));

            return response()->json([
                'status'  => true,
                'message' => "Orden #{$orden->id} cerrada. Mesa {$orden->mesa?->numero} liberada.",
                'data'    => $orden,
            ]);
        });
    }

    // ──────────────────────────────────────────────────────────
    // AGREGAR PRODUCTOS A ORDEN EXISTENTE
    // POST /ordenes/:id/detalles
    // ──────────────────────────────────────────────────────────
    public function agregarDetalles(Request $request, $id)
    {
        $orden = Orden::find($id);
        if (!$orden) {
            return response()->json(['status' => false, 'message' => 'Orden no encontrada'], 404);
        }
        if (in_array($orden->estado, ['cerrado', 'cancelado'])) {
            return response()->json(['status' => false, 'message' => "No se pueden agregar productos a una orden '{$orden->estado}'"], 400);
        }

        $request->validate([
            'detalles'               => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|integer',
            'detalles.*.cantidad'    => 'required|integer|min:1',
        ]);

        $incremento = 0;

        foreach ($request->detalles as $item) {
            $producto = Producto::find($item['producto_id']);
            if (!$producto) {
                return response()->json(['status' => false, 'message' => "Producto con ID {$item['producto_id']} no existe"], 404);
            }

            $area  = $producto->categoria->area ?? 'cocina';
            $linea = $producto->precio * $item['cantidad'];

            $existente = OrdenDetalle::where('orden_id', $orden->id)
                ->where('producto_id', $producto->id)
                ->where('estado', 'pendiente')
                ->first();

            if ($existente) {
                $nuevaCant    = $existente->cantidad + $item['cantidad'];
                $nuevoSubtotal = $producto->precio * $nuevaCant;
                $incremento   += ($nuevoSubtotal - $existente->subtotal);
                $existente->update(['cantidad' => $nuevaCant, 'subtotal' => $nuevoSubtotal]);
            } else {
                OrdenDetalle::create([
                    'orden_id'        => $orden->id,
                    'producto_id'     => $producto->id,
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $producto->precio,
                    'subtotal'        => $linea,
                    'cocinero_id'     => null,
                    'area'            => $area,
                    'estado'          => 'pendiente',
                ]);
                $incremento += $linea;
            }
        }

        $nuevoSubtotal = $orden->subtotal + $incremento;
        $orden->update(['subtotal' => $nuevoSubtotal, 'total' => $nuevoSubtotal]);

        return response()->json([
            'status'  => true,
            'message' => 'Productos agregados correctamente',
            'data'    => $orden->fresh('mesa', 'cliente', 'detalles.producto'),
        ]);
    }

    /** GET /ordenes/area/:area */
    public function ordenesPorArea($area)
    {
        if (!in_array($area, ['cocina', 'barra'])) {
            return response()->json(['status' => false, 'message' => 'Área inválida'], 400);
        }
        $detalles = OrdenDetalle::with(['orden.mesa', 'orden.cliente', 'producto'])
            ->where('area', $area)->where('estado', 'pendiente')->get();
        return response()->json(['status' => true, 'data' => $detalles]);
    }

    /** GET /ordenes/cliente/:cliente_id */
    public function ordenesPorCliente($cliente_id)
    {
        $cliente = Cliente::find($cliente_id);
        if (!$cliente) {
            return response()->json(['status' => false, 'message' => "Cliente {$cliente_id} no existe"], 404);
        }
        return response()->json([
            'status' => true,
            'data'   => Orden::with('mesa', 'detalles.producto')->where('cliente_id', $cliente_id)->get(),
        ]);
    }

    /** GET /ordenes/mesa/:mesa_id */
    public function ordenesPorMesa($mesa_id)
    {
        $mesa = Mesa::find($mesa_id);
        if (!$mesa) {
            return response()->json(['status' => false, 'message' => "Mesa {$mesa_id} no existe"], 404);
        }
        return response()->json([
            'status' => true,
            'data'   => Orden::with('cliente', 'detalles.producto')
                ->where('mesa_id', $mesa_id)
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    /** GET /ordenes/estado/:estado */
    public function ordenesPorEstado($estado)
    {
        $validos = ['pendiente', 'en_preparacion', 'listo', 'entregado', 'cerrado', 'cancelado'];
        if (!in_array($estado, $validos)) {
            return response()->json(['status' => false, 'message' => "Estado '{$estado}' no válido"], 400);
        }
        return response()->json([
            'status' => true,
            'data'   => Orden::with('mesa', 'cliente', 'detalles.producto')->where('estado', $estado)->get(),
        ]);
    }

    /** PUT /ordenes/:id/asignar-mozo */
    public function asignarMozo(Request $request, $id)
    {
        $request->validate(['mozo_id' => 'required|exists:mozos,id']);
        $orden = Orden::find($id);
        if (!$orden) {
            return response()->json(['status' => false, 'message' => 'Orden no encontrada'], 404);
        }
        $orden->update(['mozo_id' => $request->mozo_id]);
        return response()->json([
            'status'  => true,
            'message' => 'Mozo asignado correctamente',
            'data'    => $orden->load('mesa', 'cliente', 'detalles.producto'),
        ]);
    }
}
