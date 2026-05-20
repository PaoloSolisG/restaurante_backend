<?php

namespace App\Http\Controllers;

use App\Events\OrdenCreada;
use App\Models\Categoria;
use App\Models\Mesa;
use App\Models\Orden;
use App\Models\OrdenDetalle;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicCartaController extends Controller
{
    /**
     * GET /api/public/carta/{codigo}
     * Devuelve la carta completa + info de la mesa.
     * Sin autenticación — acceso público vía QR.
     */
    public function carta($codigo)
    {
        $mesa = Mesa::where('codigo', $codigo)->where('activo', true)->first();

        if (!$mesa) {
            return response()->json(['status' => false, 'message' => 'Mesa no encontrada'], 404);
        }

        $categorias = Categoria::where('activo', true)
            ->with(['productos' => function ($q) {
                $q->where('activo', true)->orderBy('nombre');
            }])
            ->orderBy('nombre')
            ->get()
            ->filter(fn($c) => $c->productos->isNotEmpty())
            ->values();

        $ordenActiva = Orden::where('mesa_id', $mesa->id)
            ->whereIn('estado', ['pendiente', 'en_preparacion'])
            ->with(['detalles' => fn($q) => $q->with('producto')])
            ->latest()
            ->first();

        return response()->json([
            'status'       => true,
            'mesa'         => $mesa,
            'categorias'   => $categorias,
            'orden_activa' => $ordenActiva,
        ]);
    }

    /**
     * POST /api/public/carta/{codigo}/pedido
     * Agrega ítems a la orden activa de la mesa (o crea una nueva).
     * Body: { cliente_nombre, items: [{producto_id, cantidad, notas_item?}] }
     */
    public function pedido(Request $request, $codigo)
    {
        $mesa = Mesa::where('codigo', $codigo)->where('activo', true)->first();

        if (!$mesa) {
            return response()->json(['status' => false, 'message' => 'Mesa no encontrada'], 404);
        }

        $request->validate([
            'cliente_nombre'           => 'required|string|max:100',
            'items'                    => 'required|array|min:1',
            'items.*.producto_id'      => 'required|integer|exists:productos,id',
            'items.*.cantidad'         => 'required|integer|min:1|max:30',
            'items.*.notas_item'       => 'nullable|string|max:200',
        ]);

        DB::beginTransaction();
        try {
            // Reusar orden pendiente de la mesa o crear nueva
            $orden = Orden::where('mesa_id', $mesa->id)
                ->where('estado', 'pendiente')
                ->where('origen', 'carta')
                ->latest()
                ->first();

            if (!$orden) {
                $orden = Orden::create([
                    'mesa_id'      => $mesa->id,
                    'estado'       => 'pendiente',
                    'tipo_consumo' => 'mesa',
                    'origen'       => 'carta',
                    'subtotal'     => 0,
                    'total'        => 0,
                ]);
                $mesa->update(['estado' => 'ocupada']);
            }

            $incremento = 0;
            foreach ($request->items as $item) {
                $producto = Producto::findOrFail($item['producto_id']);
                $subtotal = $producto->precio * $item['cantidad'];
                $incremento += $subtotal;

                OrdenDetalle::create([
                    'orden_id'       => $orden->id,
                    'producto_id'    => $producto->id,
                    'cantidad'       => $item['cantidad'],
                    'precio_unitario'=> $producto->precio,
                    'subtotal'       => $subtotal,
                    'cliente_nombre' => $request->cliente_nombre,
                    'notas_item'     => $item['notas_item'] ?? null,
                    'area'           => $producto->categoria->area ?? 'cocina',
                    'estado'         => 'pendiente',
                ]);
            }

            $orden->increment('subtotal', $incremento);
            $orden->increment('total', $incremento);

            DB::commit();

            // Notificar al KDS en tiempo real (igual que OrdenController)
            $orden->load('mesa', 'cliente', 'detalles.producto');
            event(new OrdenCreada($orden));

            return response()->json([
                'status'   => true,
                'message'  => '¡Pedido enviado! El mozo lo atenderá pronto.',
                'orden_id' => $orden->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Error al procesar el pedido'], 500);
        }
    }

    /**
     * DELETE /api/public/carta/{codigo}/detalle/{detalleId}
     * Cancela un ítem propio si aún está pendiente.
     * Body: { cliente_nombre }
     */
    public function cancelarDetalle(Request $request, $codigo, $detalleId)
    {
        $mesa = Mesa::where('codigo', $codigo)->where('activo', true)->first();
        if (!$mesa) {
            return response()->json(['status' => false, 'message' => 'Mesa no encontrada'], 404);
        }

        $request->validate(['cliente_nombre' => 'required|string']);

        $detalle = OrdenDetalle::whereHas('orden', fn($q) => $q->where('mesa_id', $mesa->id))
            ->where('id', $detalleId)
            ->where('cliente_nombre', $request->cliente_nombre)
            ->where('estado', 'pendiente')
            ->first();

        if (!$detalle) {
            return response()->json(['status' => false, 'message' => 'Ítem no encontrado o ya no se puede cancelar'], 404);
        }

        DB::beginTransaction();
        try {
            $orden = $detalle->orden;
            $orden->decrement('subtotal', $detalle->subtotal);
            $orden->decrement('total',    $detalle->subtotal);
            $detalle->delete();

            DB::commit();

            $orden->load('mesa', 'cliente', 'detalles.producto');
            event(new OrdenCreada($orden));

            return response()->json(['status' => true, 'message' => 'Ítem cancelado correctamente']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Error al cancelar'], 500);
        }
    }

    /**
     * GET /api/public/carta/{codigo}/estado
     * Devuelve el estado actual de la orden de la mesa.
     */
    public function estado($codigo)
    {
        $mesa = Mesa::where('codigo', $codigo)->where('activo', true)->first();

        if (!$mesa) {
            return response()->json(['status' => false, 'message' => 'Mesa no encontrada'], 404);
        }

        $orden = Orden::where('mesa_id', $mesa->id)
            ->whereIn('estado', ['pendiente', 'en_preparacion', 'listo'])
            ->with(['detalles' => fn($q) => $q->with('producto')->orderBy('cliente_nombre')])
            ->latest()
            ->first();

        return response()->json([
            'status' => true,
            'mesa'   => ['numero' => $mesa->numero, 'estado' => $mesa->estado],
            'orden'  => $orden,
        ]);
    }
}
