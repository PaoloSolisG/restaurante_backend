<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    /** GET /caja/estado — ¿hay caja abierta ahora? Calcula totales en tiempo real */
    public function estado()
    {
        $caja = Caja::with('usuarioApertura')
            ->where('estado', 'abierta')
            ->latest()
            ->first();

        if ($caja) {
            $ventas = Venta::where('caja_id', $caja->id)
                ->where('activo', true)
                ->select('metodo_pago', 'total', 'pagos_detalle')
                ->get();

            $totales = $this->calcularTotalesPorMetodo($ventas);

            $caja->total_efectivo = $totales['efectivo'];
            $caja->total_tarjeta  = $totales['tarjeta'];
            $caja->total_yape     = $totales['yape'];
            $caja->total_plin     = $totales['plin'];
            $caja->total_deposito = $totales['deposito'];
            $caja->total_mixto    = $totales['mixto'];
            $caja->total_ventas   = $ventas->sum('total');
        }

        return response()->json([
            'status'       => true,
            'caja_abierta' => !!$caja,
            'data'         => $caja
        ]);
    }

    /** POST /caja/abrir */
    public function abrir(Request $request)
    {
        // Verificar que no haya caja abierta
        if (Caja::where('estado', 'abierta')->exists()) {
            return response()->json([
                'status'  => false,
                'message' => 'Ya existe una caja abierta. Ciérrala antes de abrir una nueva.'
            ], 400);
        }

        $request->validate([
            'monto_inicial'   => 'required|numeric|min:0',
            'notas_apertura'  => 'nullable|string',
        ]);

        $caja = Caja::create([
            'usuario_apertura_id' => Auth::id(),
            'monto_inicial'       => $request->monto_inicial,
            'monto_esperado'      => $request->monto_inicial,
            'estado'              => 'abierta',
            'apertura_at'         => now(),
            'notas_apertura'      => $request->notas_apertura,
        ]);

        // Registrar movimiento de apertura
        if ($request->monto_inicial > 0) {
            CajaMovimiento::create([
                'caja_id'     => $caja->id,
                'usuario_id'  => Auth::id(),
                'tipo'        => 'ingreso',
                'concepto'    => 'ingreso_extra',
                'descripcion' => 'Monto inicial de apertura',
                'monto'       => $request->monto_inicial,
                'metodo_pago' => 'efectivo',
            ]);
        }

        AuditLog::registrar(
            'caja.abrir', 'Caja', $caja->id,
            "Caja #{$caja->id} abierta — Monto inicial: S/ {$caja->monto_inicial}",
            ['monto_inicial' => $caja->monto_inicial]
        );

        return response()->json([
            'status'  => true,
            'message' => 'Caja abierta correctamente',
            'data'    => $caja->load('usuarioApertura')
        ], 201);
    }

    /** POST /caja/cerrar */
    public function cerrar(Request $request)
    {
        $caja = Caja::where('estado', 'abierta')->latest()->first();

        if (!$caja) {
            return response()->json([
                'status'  => false,
                'message' => 'No hay caja abierta'
            ], 400);
        }

        $request->validate([
            'monto_real'    => 'required|numeric|min:0',
            'notas_cierre'  => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $caja) {
            // Calcular totales por método de pago de las ventas de esta caja
            $ventas = Venta::where('caja_id', $caja->id)
                ->where('activo', true)
                ->select('metodo_pago', 'total', 'pagos_detalle')
                ->get();

            $totales = $this->calcularTotalesPorMetodo($ventas);

            $totalEfectivo = $totales['efectivo'];
            $totalTarjeta  = $totales['tarjeta'];
            $totalYape     = $totales['yape'];
            $totalPlin     = $totales['plin'];
            $totalDeposito = $totales['deposito'];
            $totalMixto    = $totales['mixto'];
            $totalVentas   = $ventas->sum('total');

            // Solo egresos manuales (retiro, gasto, ajuste).
            // Las anulaciones NO se restan aquí porque la venta ya queda
            // excluida del total al pasar a activo=false — restarla también
            // produciría un doble descuento.
            $egresos = CajaMovimiento::where('caja_id', $caja->id)
                ->where('tipo', 'egreso')
                ->where('concepto', '!=', 'anulacion')
                ->sum('monto');

            // Ingresos extras manuales (sin contar apertura ni ventas)
            $ingresosExtra = CajaMovimiento::where('caja_id', $caja->id)
                ->where('tipo', 'ingreso')
                ->where('concepto', 'ingreso_extra')
                ->where('descripcion', '!=', 'Monto inicial de apertura')
                ->sum('monto');

            // total_mixto = ventas con metodo_pago='mixto' pero sin pagos_detalle.
            // Se incluye en el esperado de efectivo porque sin desglose no se puede saber
            // qué método usó realmente el cliente, y es más seguro asumirlo como cash.
            $montoEsperado = $caja->monto_inicial + $totalEfectivo + $totalMixto + $ingresosExtra - $egresos;
            $diferencia    = $request->monto_real - $montoEsperado;

            $caja->update([
                'usuario_cierre_id' => Auth::id(),
                'monto_esperado'    => $montoEsperado,
                'monto_real'        => $request->monto_real,
                'diferencia'        => $diferencia,
                'total_efectivo'    => $totalEfectivo,
                'total_tarjeta'     => $totalTarjeta,
                'total_yape'        => $totalYape,
                'total_plin'        => $totalPlin,
                'total_deposito'    => $totalDeposito,
                'total_mixto'       => $totalMixto,
                'total_ventas'      => $totalVentas,
                'estado'            => 'cerrada',
                'cierre_at'         => now(),
                'notas_cierre'      => $request->notas_cierre,
            ]);

            AuditLog::registrar(
                'caja.cerrar', 'Caja', $caja->id,
                "Caja #{$caja->id} cerrada — Total ventas: S/ {$totalVentas} — Esperado: S/ {$montoEsperado} — Real: S/ {$request->monto_real} — Diferencia: S/ {$diferencia}",
                ['total_ventas' => $totalVentas, 'monto_esperado' => $montoEsperado, 'monto_real' => $request->monto_real, 'diferencia' => $diferencia]
            );

            return response()->json([
                'status'  => true,
                'message' => 'Caja cerrada correctamente',
                'data'    => $caja->load('usuarioApertura', 'usuarioCierre')
            ]);
        });
    }

    /** POST /caja/movimiento — ingreso o egreso manual */
    public function movimiento(Request $request)
    {
        $caja = Caja::where('estado', 'abierta')->latest()->first();

        if (!$caja) {
            return response()->json(['status' => false, 'message' => 'No hay caja abierta'], 400);
        }

        $request->validate([
            'tipo'        => 'required|in:ingreso,egreso',
            'concepto'    => 'required|in:ingreso_extra,retiro,gasto,ajuste',
            'descripcion' => 'required|string',
            'monto'       => 'required|numeric|min:0.01',
            'metodo_pago' => 'required|in:efectivo,tarjeta,yape,plin,deposito,mixto',
        ]);

        $movimiento = CajaMovimiento::create([
            'caja_id'     => $caja->id,
            'usuario_id'  => Auth::id(),
            'tipo'        => $request->tipo,
            'concepto'    => $request->concepto,
            'descripcion' => $request->descripcion,
            'monto'       => $request->monto,
            'metodo_pago' => $request->metodo_pago,
        ]);

        AuditLog::registrar(
            'caja.movimiento', 'Caja', $caja->id,
            "Movimiento {$request->tipo} — {$request->descripcion} — S/ {$request->monto} ({$request->metodo_pago})",
            ['tipo' => $request->tipo, 'concepto' => $request->concepto, 'monto' => $request->monto, 'metodo_pago' => $request->metodo_pago]
        );

        return response()->json([
            'status'  => true,
            'message' => 'Movimiento registrado',
            'data'    => $movimiento
        ], 201);
    }

    /** GET /caja/historial */
    public function historial(Request $request)
    {
        $query = Caja::with('usuarioApertura', 'usuarioCierre');

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('apertura_at', [
                Carbon::parse($request->fecha_inicio)->startOfDay(),
                Carbon::parse($request->fecha_fin)->endOfDay()
            ]);
        } else {
            $query->whereDate('apertura_at', Carbon::today());
        }

        return response()->json([
            'status' => true,
            'data'   => $query->orderByDesc('apertura_at')->get()
        ]);
    }

    /**
     * Distribuye los totales por método de pago.
     * Las ventas mixtas se desglosan según pagos_detalle en lugar de acumularse en total_mixto.
     */
    private function calcularTotalesPorMetodo($ventas): array
    {
        $totales = ['efectivo' => 0, 'tarjeta' => 0, 'yape' => 0, 'plin' => 0, 'deposito' => 0, 'mixto' => 0];

        foreach ($ventas as $venta) {
            if ($venta->metodo_pago === 'mixto' && !empty($venta->pagos_detalle)) {
                // Distribuir según el desglose guardado
                foreach ($venta->pagos_detalle as $p) {
                    $metodo = $p['metodo'] ?? '';
                    if (isset($totales[$metodo])) {
                        $totales[$metodo] += floatval($p['monto'] ?? 0);
                    }
                }
            } elseif (isset($totales[$venta->metodo_pago])) {
                $totales[$venta->metodo_pago] += floatval($venta->total);
            }
        }

        return $totales;
    }

    /** GET /caja/:id — detalle de una caja con movimientos */
    public function show($id)
    {
        $caja = Caja::with([
            'usuarioApertura',
            'usuarioCierre',
            'movimientos.usuario',
            'movimientos.venta'
        ])->find($id);

        if (!$caja) {
            return response()->json(['status' => false, 'message' => 'Caja no encontrada'], 404);
        }

        return response()->json(['status' => true, 'data' => $caja]);
    }
}
