<?php

namespace App\Http\Controllers;

use App\Exports\CajaResumenExport;
use App\Exports\CancelacionesExport;
use App\Exports\MozosRendimientoExport;
use App\Exports\ProductosVendidosExport;
use App\Exports\VentasExport;
use App\Models\Orden;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReporteController extends Controller
{
    /**
     * GET /reportes/resumen?desde=&hasta=
     * KPIs rápidos para la vista de reportes del frontend.
     */
    public function resumen(Request $request)
    {
        $desde = Carbon::parse($request->desde ?? today())->startOfDay();
        $hasta = Carbon::parse($request->hasta ?? today())->endOfDay();

        // ── Ventas ──────────────────────────────────────────────
        $ventas = Venta::where('activo', true)
            ->whereBetween('created_at', [$desde, $hasta]);

        $totalVentas     = (clone $ventas)->sum('total');
        $countVentas     = (clone $ventas)->count();
        $totalPropinas   = (clone $ventas)->sum('propina');
        $totalDescuentos = (clone $ventas)->sum('descuento');

        $porMetodo = (clone $ventas)->select('metodo_pago', DB::raw('SUM(total) as monto'), DB::raw('COUNT(*) as cantidad'))
            ->groupBy('metodo_pago')->get();

        // ── Productos más vendidos ───────────────────────────────
        $topProductos = VentaDetalle::select(
                'nombre_producto',
                DB::raw('SUM(cantidad) as total_cantidad'),
                DB::raw('SUM(subtotal) as total_ingresos')
            )
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.activo', true)
            ->whereBetween('ventas.created_at', [$desde, $hasta])
            ->groupBy('nombre_producto')
            ->orderByDesc('total_cantidad')
            ->limit(10)
            ->get();

        // ── Evolución diaria ─────────────────────────────────────
        $evolucion = Venta::where('activo', true)
            ->whereBetween('created_at', [$desde, $hasta])
            ->select(DB::raw('DATE(created_at) as fecha'), DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as cantidad'))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        // ── Cancelaciones ────────────────────────────────────────
        $cancelaciones = Orden::where('estado', 'cancelado')
            ->whereBetween('created_at', [$desde, $hasta])
            ->count();

        $montoCancelado = Orden::where('estado', 'cancelado')
            ->whereBetween('created_at', [$desde, $hasta])
            ->sum('total');

        // ── Mozos ────────────────────────────────────────────────
        $topMozos = Venta::select(
                DB::raw("CONCAT(mozos.nombre, ' ', IFNULL(mozos.apellido,'')) as mozo"),
                DB::raw('SUM(ventas.total) as total'),
                DB::raw('COUNT(ventas.id) as ventas')
            )
            ->join('mozos', 'mozos.id', '=', 'ventas.mozo_id')
            ->where('ventas.activo', true)
            ->whereBetween('ventas.created_at', [$desde, $hasta])
            ->groupBy('mozos.id', 'mozos.nombre', 'mozos.apellido')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => true,
            'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],
            'ventas' => [
                'total'          => round($totalVentas, 2),
                'cantidad'       => $countVentas,
                'propinas'       => round($totalPropinas, 2),
                'descuentos'     => round($totalDescuentos, 2),
                'por_metodo'     => $porMetodo,
                'evolucion'      => $evolucion,
            ],
            'productos_top'  => $topProductos,
            'mozos_top'      => $topMozos,
            'cancelaciones'  => [
                'cantidad' => $cancelaciones,
                'monto'    => round($montoCancelado, 2),
            ],
        ]);
    }

    /**
     * GET /reportes/descargar/{tipo}?desde=&hasta=&metodo_pago=
     * Descarga el Excel del reporte solicitado.
     */
    public function descargar(Request $request, string $tipo)
    {
        $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $desde = $request->desde;
        $hasta = $request->hasta;

        $nombre = "reporte_{$tipo}_{$desde}_{$hasta}.xlsx";

        $export = match ($tipo) {
            'ventas'      => new VentasExport($desde, $hasta, $request->metodo_pago),
            'productos'   => new ProductosVendidosExport($desde, $hasta),
            'mozos'       => new MozosRendimientoExport($desde, $hasta),
            'caja'        => new CajaResumenExport($desde, $hasta),
            'cancelaciones' => new CancelacionesExport($desde, $hasta),
            default       => null,
        };

        if (!$export) {
            return response()->json(['status' => false, 'message' => "Reporte '{$tipo}' no existe"], 404);
        }

        return Excel::download($export, $nombre);
    }
}
