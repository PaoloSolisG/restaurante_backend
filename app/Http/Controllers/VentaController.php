<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\Orden;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\FacturacionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Totales solo de ventas activas
        $ventasActivas = $ventas->where('activo', true);

        return response()->json([
            'status' => true,
            'data'   => $ventas,
            'anuladas' => $ventas->where('activo', false)->count(),
            'resumen' => [
                'total_ventas'    => $ventasActivas->count(),
                'total_periodo'   => $ventasActivas->sum('total'),
                'total_efectivo'  => $ventasActivas->where('metodo_pago', 'efectivo')->sum('total'),
                'total_tarjeta'   => $ventasActivas->where('metodo_pago', 'tarjeta')->sum('total'),
                'total_yape'      => $ventasActivas->where('metodo_pago', 'yape')->sum('total'),
                'total_plin'      => $ventasActivas->where('metodo_pago', 'plin')->sum('total'),
                'total_deposito'  => $ventasActivas->where('metodo_pago', 'deposito')->sum('total'),
                'total_mixto'     => $ventasActivas->where('metodo_pago', 'mixto')->sum('total'),
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
     * Convierte una orden cerrada en venta, registra en caja y emite comprobante electrónico.
     *
     * Campos opcionales del request:
     *   - emitir_comprobante (bool, default: true) — si false, omite la emisión a Naniva
     */
    public function store(Request $request)
    {
        $request->validate([
            'orden_id'           => 'required|exists:ordenes,id',
            'metodo_pago'        => 'required|in:efectivo,tarjeta,yape,plin,deposito,mixto',
            'monto_recibido'     => 'required|numeric|min:0',
            'propina'            => 'nullable|numeric|min:0',
            'descuento'          => 'nullable|numeric|min:0',
            'pagos_detalle'      => 'nullable|array',
            'emitir_comprobante' => 'nullable|boolean',
        ]);

        $caja = Caja::where('estado', 'abierta')->latest()->first();
        if (!$caja) {
            return response()->json([
                'status'  => false,
                'message' => 'No hay caja abierta. Abre la caja antes de registrar ventas.',
            ], 400);
        }

        $orden = Orden::with('detalles.producto')->find($request->orden_id);

        if ($orden->estado !== 'cerrado') {
            return response()->json([
                'status'  => false,
                'message' => 'La orden debe estar cerrada para registrar una venta',
            ], 400);
        }

        if (Venta::where('orden_id', $orden->id)->exists()) {
            return response()->json([
                'status'  => false,
                'message' => 'Esta orden ya tiene una venta registrada',
            ], 400);
        }

        // ── 1. Persistir venta en base de datos (dentro de transacción) ──────
        $venta = DB::transaction(function () use ($request, $orden, $caja) {
            $IGV_DIV       = 1.105;
            $propina       = $request->propina ?? 0;
            $descuento     = $request->descuento ?? 0;
            $baseImponible = round($orden->total / $IGV_DIV, 2);
            $igv           = round($orden->total - $baseImponible, 2);
            $total         = round($orden->total + $propina - $descuento, 2);
            $vuelto        = max(0, round($request->monto_recibido - $total, 2));

            $venta = Venta::create([
                'orden_id'       => $orden->id,
                'caja_id'        => $caja->id,
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

            // Registrar ingreso(s) en caja — un movimiento por método de pago
            $desc = "Venta #{$venta->id} - Orden #{$orden->id}";
            if ($request->metodo_pago === 'mixto' && !empty($request->pagos_detalle)) {
                foreach ($request->pagos_detalle as $pago) {
                    CajaMovimiento::create([
                        'caja_id'     => $caja->id,
                        'usuario_id'  => Auth::id(),
                        'venta_id'    => $venta->id,
                        'tipo'        => 'ingreso',
                        'concepto'    => 'venta',
                        'descripcion' => $desc,
                        'monto'       => $pago['monto'],
                        'metodo_pago' => $pago['metodo'],
                    ]);
                }
            } else {
                CajaMovimiento::create([
                    'caja_id'     => $caja->id,
                    'usuario_id'  => Auth::id(),
                    'venta_id'    => $venta->id,
                    'tipo'        => 'ingreso',
                    'concepto'    => 'venta',
                    'descripcion' => $desc,
                    'monto'       => $total,
                    'metodo_pago' => $request->metodo_pago,
                ]);
            }

            return $venta;
        });

        // ── 2. Emitir comprobante electrónico (fuera de transacción) ─────────
        // Si Naniva falla, la venta queda guardada y puede reintentarse desde
        // POST /ventas/{id}/comprobante/emitir
        $comprobanteInfo = null;
        $emitir = $request->boolean('emitir_comprobante', true);

        if ($emitir) {
            $facturacion = app(FacturacionService::class);
            $result      = $facturacion->emitir($venta->load('cliente', 'detalles'));

            $venta->update([
                'tipo_comprobante'    => $result['tipo_comprobante']   ?? null,
                'serie_comprobante'   => $result['serie_comprobante']  ?? null,
                'numero_comprobante'  => $result['numero_comprobante'] ?? null,
                'filename_comprobante'=> $result['filename']           ?? null,
                'estado_sunat'        => $result['estado_sunat']       ?? null,
                'error_comprobante'   => $result['error']              ?? null,
            ]);

            $comprobanteInfo = [
                'emitido'            => $result['success'],
                'numero_comprobante' => $venta->numero_comprobante,
                'estado_sunat'       => $venta->estado_sunat,
                'error'              => $result['success'] ? null : $result['error'],
            ];
        }

        AuditLog::registrar(
            'venta.crear', 'Venta', $venta->id,
            "Venta #{$venta->id} registrada — Mesa {$venta->mesa_id} — S/ {$venta->total} — {$venta->metodo_pago}",
            ['total' => $venta->total, 'metodo_pago' => $venta->metodo_pago, 'orden_id' => $venta->orden_id]
        );

        return response()->json([
            'status'      => true,
            'message'     => "Venta #{$venta->id} registrada correctamente",
            'data'        => $venta->load('mesa', 'cliente', 'mozo', 'detalles'),
            'comprobante' => $comprobanteInfo,
        ], 201);
    }

    /**
     * POST /ventas/{id}/anular — Comunicación de Baja (RA) directa.
     * Anula el comprobante ante SUNAT sin generar nuevo documento.
     * Si la venta no tiene comprobante electrónico, solo revierte la caja.
     */
    public function anular(Request $request, $id)
    {
        $request->validate([
            'motivo' => 'required|string|min:5|max:500',
        ]);

        $venta = Venta::find($id);
        if (!$venta) {
            return response()->json(['status' => false, 'message' => 'Venta no encontrada'], 404);
        }

        if (!$venta->activo) {
            return response()->json(['status' => false, 'message' => 'Esta venta ya fue anulada'], 400);
        }

        $filenameAnulacion = null;
        $errorBaja = null;

        // Si tiene comprobante, intentar baja ante SUNAT
        if ($venta->numero_comprobante) {
            $facturacion = app(FacturacionService::class);
            $result = $facturacion->darBaja($venta, $request->motivo);

            if (!$result['success']) {
                $errorBaja = $result['error'] ?? 'Error al dar de baja el comprobante';
            } else {
                $filenameAnulacion = $result['filename'] ?? null;
            }
        }

        DB::transaction(function () use ($venta, $request, $filenameAnulacion) {
            // Registrar egreso(s) de reversa — un movimiento por método de pago
            // (solo como auditoría; NO se resta del monto_esperado porque la venta
            //  ya queda excluida al pasar a activo=false)
            $descAnul = "Anulación Venta #{$venta->id} — {$request->motivo}";
            if ($venta->metodo_pago === 'mixto' && !empty($venta->pagos_detalle)) {
                foreach ($venta->pagos_detalle as $pago) {
                    CajaMovimiento::create([
                        'caja_id'     => $venta->caja_id,
                        'usuario_id'  => Auth::id(),
                        'venta_id'    => $venta->id,
                        'tipo'        => 'egreso',
                        'concepto'    => 'anulacion',
                        'descripcion' => $descAnul,
                        'monto'       => $pago['monto'],
                        'metodo_pago' => $pago['metodo'],
                    ]);
                }
            } else {
                CajaMovimiento::create([
                    'caja_id'     => $venta->caja_id,
                    'usuario_id'  => Auth::id(),
                    'venta_id'    => $venta->id,
                    'tipo'        => 'egreso',
                    'concepto'    => 'anulacion',
                    'descripcion' => $descAnul,
                    'monto'       => $venta->total,
                    'metodo_pago' => $venta->metodo_pago,
                ]);
            }

            $venta->update([
                'activo'             => false,
                'motivo_anulacion'   => $request->motivo,
                'anulado_en'         => now(),
                'tipo_anulacion'     => 'RA',
                'filename_anulacion' => $filenameAnulacion,
            ]);
        });

        AuditLog::registrar(
            'venta.anular', 'Venta', $venta->id,
            "Venta #{$venta->id} anulada — Motivo: {$request->motivo}",
            ['total' => $venta->total, 'metodo_pago' => $venta->metodo_pago, 'motivo' => $request->motivo, 'error_sunat' => $errorBaja]
        );

        return response()->json([
            'status'  => true,
            'message' => $errorBaja
                ? "Venta anulada localmente, pero la baja ante SUNAT falló: {$errorBaja}"
                : 'Venta anulada correctamente (Comunicación de Baja)',
            'data'    => $venta->fresh(),
            'warning' => $errorBaja,
        ]);
    }

    /**
     * POST /ventas/{id}/nota-credito — Emite Nota de Crédito (07).
     * Genera un nuevo comprobante que anula legalmente al original.
     */
    public function notaCredito(Request $request, $id)
    {
        $request->validate([
            'motivo' => 'required|string|min:5|max:500',
        ]);

        $venta = Venta::with(['cliente', 'detalles'])->find($id);
        if (!$venta) {
            return response()->json(['status' => false, 'message' => 'Venta no encontrada'], 404);
        }

        if (!$venta->activo) {
            return response()->json(['status' => false, 'message' => 'Esta venta ya fue anulada'], 400);
        }

        if (!$venta->numero_comprobante) {
            return response()->json([
                'status'  => false,
                'message' => 'Esta venta no tiene comprobante original. Use /anular en su lugar.',
            ], 400);
        }

        $facturacion = app(FacturacionService::class);
        $result = $facturacion->emitirNotaCredito($venta, $request->motivo);

        if (!$result['success']) {
            return response()->json([
                'status'  => false,
                'message' => 'Error al emitir Nota de Crédito: ' . ($result['error'] ?? 'Desconocido'),
            ], 502);
        }

        DB::transaction(function () use ($venta, $request, $result) {
            $descNC = "Nota de Crédito Venta #{$venta->id} — {$request->motivo}";
            if ($venta->metodo_pago === 'mixto' && !empty($venta->pagos_detalle)) {
                foreach ($venta->pagos_detalle as $pago) {
                    CajaMovimiento::create([
                        'caja_id'     => $venta->caja_id,
                        'usuario_id'  => Auth::id(),
                        'venta_id'    => $venta->id,
                        'tipo'        => 'egreso',
                        'concepto'    => 'anulacion',
                        'descripcion' => $descNC,
                        'monto'       => $pago['monto'],
                        'metodo_pago' => $pago['metodo'],
                    ]);
                }
            } else {
                CajaMovimiento::create([
                    'caja_id'     => $venta->caja_id,
                    'usuario_id'  => Auth::id(),
                    'venta_id'    => $venta->id,
                    'tipo'        => 'egreso',
                    'concepto'    => 'anulacion',
                    'descripcion' => $descNC,
                    'monto'       => $venta->total,
                    'metodo_pago' => $venta->metodo_pago,
                ]);
            }

            $venta->update([
                'activo'             => false,
                'motivo_anulacion'   => $request->motivo,
                'anulado_en'         => now(),
                'tipo_anulacion'     => 'NC',
                'filename_anulacion' => $result['filename'] ?? null,
            ]);
        });

        AuditLog::registrar(
            'venta.nota_credito', 'Venta', $venta->id,
            "Nota de Crédito emitida para Venta #{$venta->id} — Motivo: {$request->motivo}",
            ['total' => $venta->total, 'numero_nc' => $result['numero'] ?? null, 'motivo' => $request->motivo]
        );

        return response()->json([
            'status'  => true,
            'message' => 'Nota de Crédito emitida correctamente. Venta anulada.',
            'data'    => [
                'venta'      => $venta->fresh(),
                'nota_credito' => [
                    'numero'   => $result['numero'] ?? null,
                    'filename' => $result['filename'] ?? null,
                    'estado'   => $result['estado'] ?? null,
                ],
            ],
        ]);
    }
}
