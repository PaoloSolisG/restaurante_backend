<?php

namespace App\Http\Controllers;

use App\Models\Orden;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    /** GET /ventas */
    public function index(Request $request)
    {
        $query = Venta::with(['mesa', 'cliente', 'mozo', 'detalles']);

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->fecha_inicio)->startOfDay(),
                Carbon::parse($request->fecha_fin)->endOfDay()
            ]);
        } else {
            $query->whereDate('created_at', Carbon::today());
        }

        if ($request->filled('metodo_pago')) {
            $query->where('metodo_pago', $request->metodo_pago);
        }

        $ventas = $query->orderByDesc('created_at')->get();

        return response()->json([
            'status' => true,
            'data'   => $ventas,
            'resumen' => [
                'total_ventas'    => $ventas->count(),
                'total_periodo'   => $ventas->sum('total'),
                'total_efectivo'  => $ventas->where('metodo_pago', 'efectivo')->sum('total'),
                'total_tarjeta'   => $ventas->where('metodo_pago', 'tarjeta')->sum('total'),
                'total_yape'      => $ventas->where('metodo_pago', 'yape')->sum('total'),
                'total_plin'      => $ventas->where('metodo_pago', 'plin')->sum('total'),
                'total_deposito'  => $ventas->where('metodo_pago', 'deposito')->sum('total'),
                'total_mixto'     => $ventas->where('metodo_pago', 'mixto')->sum('total'),
            ]
        ]);
    }

    /** GET /ventas/:id */
    public function show($id)
    {
        $venta = Venta::with(['orden', 'mesa', 'cliente', 'mozo', 'detalles'])->find($id);
        if (!$venta) {
            return response()->json(['status' => false, 'message' => 'Venta no encontrada'], 404);
        }
        return response()->json(['status' => true, 'data' => $venta]);
    }

    /**
     * POST /ventas
     * Convierte una orden cerrada en venta
     */
    public function store(Request $request)
    {
        $request->validate([
            'orden_id'       => 'required|exists:ordenes,id',
            'metodo_pago'    => 'required|in:efectivo,tarjeta,yape,plin,deposito,mixto',
            'monto_recibido' => 'required|numeric|min:0',
            'propina'        => 'nullable|numeric|min:0',
            'descuento'      => 'nullable|numeric|min:0',
            'pagos_detalle'  => 'nullable|array',
        ]);

        $orden = Orden::with('detalles.producto')->find($request->orden_id);

        if ($orden->estado !== 'cerrado') {
            return response()->json([
                'status'  => false,
                'message' => 'La orden debe estar cerrada para registrar una venta'
            ], 400);
        }

        if (Venta::where('orden_id', $orden->id)->exists()) {
            return response()->json([
                'status'  => false,
                'message' => 'Esta orden ya tiene una venta registrada'
            ], 400);
        }

        return DB::transaction(function () use ($request, $orden) {
            $IGV_DIV        = 1.105;
            $propina        = $request->propina ?? 0;
            $descuento      = $request->descuento ?? 0;
            $baseImponible  = round($orden->total / $IGV_DIV, 2);
            $igv            = round($orden->total - $baseImponible, 2);
            $total          = round($orden->total + $propina - $descuento, 2);
            $vuelto         = max(0, round($request->monto_recibido - $total, 2));

            $venta = Venta::create([
                'orden_id'       => $orden->id,
                'mesa_id'        => $orden->mesa_id,
                'cliente_id'     => $orden->cliente_id,
                'mozo_id'        => $orden->mozo_id,
                'tipo_consumo'   => $orden->tipo_consumo,
                'base_imponible' => $baseImponible,
                'igv'            => $igv,
                'propina'        => $propina,
                'descuento'      => $descuento,
                'total'          => $total,
                'metodo_pago'    => $request->metodo_pago,
                'monto_recibido' => $request->monto_recibido,
                'vuelto'         => $vuelto,
                'pagos_detalle'  => $request->pagos_detalle,
                'notas'          => $orden->notas,
            ]);

            // Snapshot de productos
            foreach ($orden->detalles as $detalle) {
                VentaDetalle::create([
                    'venta_id'        => $venta->id,
                    'producto_id'     => $detalle->producto_id,
                    'nombre_producto' => $detalle->producto?->nombre ?? 'Producto eliminado',
                    'codigo_producto' => $detalle->producto?->codigo ?? null,
                    'precio_unitario' => $detalle->precio_unitario,
                    'cantidad'        => $detalle->cantidad,
                    'subtotal'        => $detalle->subtotal,
                ]);
            }

            return response()->json([
                'status'  => true,
                'message' => "Venta #{$venta->id} registrada correctamente",
                'data'    => $venta->load('mesa', 'cliente', 'mozo', 'detalles'),
            ], 201);
        });
    }
}
