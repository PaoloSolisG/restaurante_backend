<?php

namespace App\Http\Controllers;

use App\Events\OrdenCreada;
use App\Models\Mozo;
use App\Models\Orden;
use Illuminate\Http\Request;

class MozoDashboardController extends Controller
{
    /**
     * GET /api/mozo/mis-ordenes
     * Retorna:
     *  - pending_sin_asignar: órdenes de carta sin mozo, estado pendiente
     *  - mis_ordenes: órdenes asignadas al mozo logueado (activas)
     *  - mozo: el registro mozo vinculado al usuario actual (o null)
     */
    public function misOrdenes(Request $request)
    {
        $usuario = $request->user();

        $mozo = Mozo::where('usuario_id', $usuario->id)->first();

        $sinAsignar = Orden::whereNull('mozo_id')
            ->where('origen', 'carta')
            ->whereIn('estado', ['pendiente', 'en_preparacion'])
            ->with(['mesa', 'detalles.producto'])
            ->orderBy('created_at')
            ->get();

        $misOrdenes = collect();
        if ($mozo) {
            $misOrdenes = Orden::where('mozo_id', $mozo->id)
                ->whereIn('estado', ['pendiente', 'en_preparacion', 'listo'])
                ->with(['mesa', 'detalles.producto'])
                ->orderBy('created_at')
                ->get();
        }

        return response()->json([
            'status'            => true,
            'mozo'              => $mozo,
            'sin_asignar'       => $sinAsignar,
            'mis_ordenes'       => $misOrdenes,
        ]);
    }

    /**
     * POST /api/ordenes/{id}/asignarme
     * Asigna la orden al mozo del usuario logueado y cambia estado a en_preparacion.
     */
    public function asignarme(Request $request, $id)
    {
        $usuario = $request->user();

        $mozo = Mozo::where('usuario_id', $usuario->id)->first();
        if (!$mozo) {
            return response()->json([
                'status'  => false,
                'message' => 'Tu usuario no tiene un perfil de mozo vinculado.',
            ], 403);
        }

        $orden = Orden::with(['mesa', 'detalles.producto'])->find($id);
        if (!$orden) {
            return response()->json(['status' => false, 'message' => 'Orden no encontrada'], 404);
        }

        if ($orden->mozo_id && $orden->mozo_id !== $mozo->id) {
            return response()->json([
                'status'  => false,
                'message' => 'Esta orden ya fue asignada a otro mozo.',
            ], 409);
        }

        $orden->update([
            'mozo_id' => $mozo->id,
            // estado se mantiene 'pendiente' — el cocinero lo mueve a en_preparacion desde el KDS
        ]);

        $orden->load('mesa', 'cliente', 'detalles.producto');
        event(new OrdenCreada($orden));

        return response()->json([
            'status'  => true,
            'message' => 'Orden asignada correctamente.',
            'data'    => $orden,
        ]);
    }

    /**
     * POST /api/ordenes/{id}/liberar
     * Libera una orden asignada (vuelve a mozo_id = null, estado = pendiente).
     */
    public function liberar(Request $request, $id)
    {
        $usuario = $request->user();

        $mozo = Mozo::where('usuario_id', $usuario->id)->first();
        if (!$mozo) {
            return response()->json(['status' => false, 'message' => 'Sin perfil de mozo.'], 403);
        }

        $orden = Orden::find($id);
        if (!$orden) {
            return response()->json(['status' => false, 'message' => 'Orden no encontrada'], 404);
        }

        if ($orden->mozo_id !== $mozo->id) {
            return response()->json(['status' => false, 'message' => 'No puedes liberar una orden que no es tuya.'], 403);
        }

        $orden->update(['mozo_id' => null, 'estado' => 'pendiente']);
        $orden->load('mesa', 'cliente', 'detalles.producto');
        event(new OrdenCreada($orden));

        return response()->json(['status' => true, 'message' => 'Orden liberada.', 'data' => $orden]);
    }
}
