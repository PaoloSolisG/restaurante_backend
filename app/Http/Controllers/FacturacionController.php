<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CajaMovimiento;
use App\Models\Venta;
use App\Services\FacturacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FacturacionController extends Controller
{
    public function __construct(private FacturacionService $facturacion) {}

    /** GET /facturacion/status — verifica conexión con Naniva */
    public function status()
    {
        $result = $this->facturacion->status();

        return response()->json([
            'status'  => $result['ok'],
            'message' => $result['ok'] ? 'Conexión con Naniva OK' : 'Sin conexión con Naniva',
            'data'    => $result['data'] ?? null,
            'error'   => $result['error'] ?? null,
        ], $result['ok'] ? 200 : 503);
    }

    /**
     * GET /ventas/{id}/comprobante/pdf
     * Devuelve el PDF en base64 para que el frontend lo descargue o imprima.
     */
    public function pdf($id)
    {
        $venta = Venta::find($id);

        if (!$venta) {
            return response()->json(['status' => false, 'message' => 'Venta no encontrada'], 404);
        }

        if (!$venta->filename_comprobante) {
            return response()->json([
                'status'  => false,
                'message' => 'Esta venta no tiene comprobante electrónico emitido',
            ], 404);
        }

        $result = $this->facturacion->getPdf($venta->filename_comprobante);

        if (!$result['success']) {
            return response()->json([
                'status'  => false,
                'message' => $result['error'] ?? 'No se pudo obtener el PDF',
            ], 502);
        }

        return response()->json([
            'status'      => true,
            'numero'      => $venta->numero_comprobante,
            'filename'    => $venta->filename_comprobante,
            'pdf_base64'  => $result['pdf_base64'],
        ]);
    }

    /**
     * POST /ventas/{id}/comprobante/reenviar
     * Reenvía el comprobante a SUNAT (útil si quedó pendiente).
     */
    public function reenviar($id)
    {
        $venta = Venta::find($id);

        if (!$venta) {
            return response()->json(['status' => false, 'message' => 'Venta no encontrada'], 404);
        }

        if (!$venta->filename_comprobante) {
            return response()->json([
                'status'  => false,
                'message' => 'Esta venta no tiene comprobante electrónico',
            ], 404);
        }

        $result = $this->facturacion->reenviar($venta->filename_comprobante);

        if ($result['success']) {
            $estadoNuevo = $result['data']['data']['estado'] ?? $venta->estado_sunat;
            $venta->update(['estado_sunat' => $estadoNuevo]);
        }

        return response()->json([
            'status'  => $result['success'],
            'message' => $result['success'] ? 'Comprobante reenviado a SUNAT' : $result['error'],
            'data'    => $result['data'] ?? null,
        ], $result['success'] ? 200 : 502);
    }

    /**
     * POST /ventas/{id}/comprobante/emitir
     * Emite (o re-emite) el comprobante de una venta que falló la primera vez.
     */
    public function emitir($id)
    {
        $venta = Venta::with(['cliente', 'detalles'])->find($id);

        if (!$venta) {
            return response()->json(['status' => false, 'message' => 'Venta no encontrada'], 404);
        }

        if ($venta->numero_comprobante && $venta->estado_sunat === 'Aceptado') {
            return response()->json([
                'status'  => false,
                'message' => "Esta venta ya tiene comprobante emitido: {$venta->numero_comprobante}",
            ], 409);
        }

        $result = $this->facturacion->emitir($venta);

        $venta->update([
            'tipo_comprobante'   => $result['tipo_comprobante']   ?? $venta->tipo_comprobante,
            'serie_comprobante'  => $result['serie_comprobante']  ?? $venta->serie_comprobante,
            'numero_comprobante' => $result['numero_comprobante'] ?? $venta->numero_comprobante,
            'filename_comprobante' => $result['filename']         ?? $venta->filename_comprobante,
            'estado_sunat'       => $result['estado_sunat']       ?? $venta->estado_sunat,
            'error_comprobante'  => $result['error'],
        ]);

        AuditLog::registrar(
            $result['success'] ? 'comprobante.emitir' : 'comprobante.emitir.error',
            'Venta', $venta->id,
            $result['success']
                ? "Comprobante {$venta->numero_comprobante} emitido para Venta #{$venta->id}"
                : "Error al emitir comprobante Venta #{$venta->id}: {$result['error']}",
            ['numero' => $venta->numero_comprobante, 'estado_sunat' => $venta->estado_sunat, 'error' => $result['error'] ?? null]
        );

        return response()->json([
            'status'  => $result['success'],
            'message' => $result['success']
                ? "Comprobante {$venta->numero_comprobante} emitido correctamente"
                : "Error al emitir comprobante: {$result['error']}",
            'data'    => $venta->fresh(),
        ], $result['success'] ? 200 : 502);
    }



    public function xml($id)
    {
        $venta = Venta::find($id);
        if (!$venta || !$venta->filename_comprobante) {
            return response()->json(['status' => false, 'message' => 'Sin comprobante'], 404);
        }
        $result = $this->facturacion->getXml($venta->filename_comprobante);
        if (!$result['success']) {
            return response()->json(['status' => false, 'message' => $result['error']], 502);
        }
        return response($result['xml'], 200)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', "attachment; filename=\"{$venta->filename_comprobante}.xml\"");
    }

    public function cdr($id)
    {
        $venta = Venta::find($id);
        if (!$venta || !$venta->filename_comprobante) {
            return response()->json(['status' => false, 'message' => 'Sin comprobante'], 404);
        }
        $result = $this->facturacion->getCdr($venta->filename_comprobante);
        if (!$result['success']) {
            return response()->json(['status' => false, 'message' => $result['error']], 502);
        }
        return response()->json(['status' => true, 'cdr_base64' => $result['cdr_base64']]);
    }

    public function consultarEstado($id)
    {
        $venta = Venta::find($id);
        if (!$venta) {
            return response()->json(['status' => false, 'message' => 'Venta no encontrada'], 404);
        }

        // Si tiene ticket de baja → consultar estado del ticket (RA/RC)
        if ($venta->ticket_baja) {
            $result = $this->facturacion->consultarTicketBaja(
                $venta->ticket_baja,
                $venta->fecha_baja       ?? now()->format('Y-m-d'),
                $venta->correlativo_baja ?? '1',
            );

            if ($result['success']) {
                $updates = ['estado_baja' => $result['estado_baja']];
                // Código 9999 = SUNAT no respondió aún (beta/pendiente)
                if ($result['codigo'] !== '9999') {
                    $updates['estado_sunat'] = $result['estado_baja'] === 'Aceptado' ? 'Anulado' : 'Error Baja';
                }
                $venta->update($updates);
            }

            return response()->json([
                'status'       => $result['success'],
                'message'      => $result['success'] ? 'Estado de baja consultado' : $result['error'],
                'estado_baja'  => $result['estado_baja'] ?? $venta->estado_baja,
                'codigo_sunat' => $result['codigo']  ?? null,
                'mensaje_sunat' => $result['mensaje'] ?? null,
                'ticket'       => $venta->ticket_baja,
                'data'         => $venta->fresh(['detalles', 'cliente', 'mesa']),
            ]);
        }

        // Sin ticket → devolver estado actual de la DB (Naniva no tiene endpoint por filename)
        if (!$venta->numero_comprobante) {
            return response()->json(['status' => false, 'message' => 'Sin comprobante registrado'], 404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Estado actual del comprobante',
            'data'    => [
                'estado'              => $venta->estado_sunat,
                'numero_comprobante'  => $venta->numero_comprobante,
                'filename'            => $venta->filename_comprobante,
            ],
        ]);
    }

    /**
     * POST /ventas/{id}/comprobante/dar-baja
     * Genera RA (factura) o RC (boleta) ante SUNAT y marca la venta como anulada.
     */
    public function darBaja($id, Request $request)
    {
        $venta = Venta::with(['cliente', 'detalles'])->find($id);

        if (!$venta) {
            return response()->json(['status' => false, 'message' => 'Venta no encontrada'], 404);
        }

        if (!$venta->numero_comprobante) {
            return response()->json(['status' => false, 'message' => 'Esta venta no tiene comprobante electrónico emitido'], 422);
        }

        if (!$venta->activo) {
            return response()->json(['status' => false, 'message' => 'Esta venta ya fue anulada'], 409);
        }

        $motivo = trim($request->input('motivo', 'ANULACION DE OPERACION'));
        if (strlen($motivo) < 5) {
            return response()->json(['status' => false, 'message' => 'El motivo debe tener al menos 5 caracteres'], 422);
        }

        $result = $this->facturacion->darBaja($venta, $motivo);

        if ($result['success']) {
            $tipoBaja = $venta->tipo_comprobante === '01' ? 'RA' : 'RC';
            $venta->update([
                'activo'            => false,
                'estado_sunat'      => 'Anulado',
                'motivo_anulacion'  => $motivo,
                'anulado_en'        => now(),
                'tipo_anulacion'    => $tipoBaja,
                'ticket_baja'       => $result['ticket']           ?? null,
                'fecha_baja'        => $result['fecha_baja']       ?? now()->format('Y-m-d'),
                'correlativo_baja'  => $result['correlativo_baja'] ?? '1',
                'estado_baja'       => $result['estado_baja']      ?? 'Pendiente',
            ]);

            // Registrar egreso(s) en caja — auditoría por método de pago
            // No afectan monto_esperado porque la venta pasa a activo=false
            if ($venta->caja_id) {
                $descBaja = "Baja {$tipoBaja} #{$venta->numero_comprobante} — {$motivo}";
                if ($venta->metodo_pago === 'mixto' && !empty($venta->pagos_detalle)) {
                    foreach ($venta->pagos_detalle as $pago) {
                        CajaMovimiento::create([
                            'caja_id'     => $venta->caja_id,
                            'usuario_id'  => Auth::id(),
                            'venta_id'    => $venta->id,
                            'tipo'        => 'egreso',
                            'concepto'    => 'anulacion',
                            'descripcion' => $descBaja,
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
                        'descripcion' => $descBaja,
                        'monto'       => $venta->total,
                        'metodo_pago' => $venta->metodo_pago,
                    ]);
                }
            }
        }

        AuditLog::registrar(
            $result['success'] ? 'comprobante.dar_baja' : 'comprobante.dar_baja.error',
            'Venta', $venta->id,
            $result['success']
                ? "Baja {$result['tipo_baja']} de {$venta->numero_comprobante} — {$motivo}"
                : "Error dar de baja {$venta->numero_comprobante}: " . ($result['error'] ?? ''),
            ['numero' => $venta->numero_comprobante, 'tipo_baja' => $result['tipo_baja'] ?? null, 'ticket' => $result['ticket'] ?? null, 'motivo' => $motivo]
        );

        return response()->json([
            'status'  => $result['success'],
            'message' => $result['success']
                ? "Comprobante {$venta->numero_comprobante} dado de baja ante SUNAT ({$result['tipo_baja']})"
                : ($result['error'] ?? 'Error al dar de baja'),
            'data'    => $result,
        ], $result['success'] ? 200 : 502);
    }

    /**
     * POST /ventas/{id}/comprobante/consultar-ticket
     * Consulta el estado del ticket de baja (RA/RC) ante SUNAT.
     */
    public function consultarTicketBaja($id)
    {
        $venta = Venta::find($id);

        if (!$venta) {
            return response()->json(['status' => false, 'message' => 'Venta no encontrada'], 404);
        }

        if (!$venta->ticket_baja) {
            return response()->json(['status' => false, 'message' => 'Esta venta no tiene ticket de baja registrado'], 422);
        }

        $result = $this->facturacion->consultarTicketBaja(
            $venta->ticket_baja,
            $venta->fecha_baja ?? now()->format('Y-m-d'),
            $venta->correlativo_baja ?? '1',
        );

        if ($result['success']) {
            $venta->update(['estado_baja' => $result['estado_baja']]);
        }

        return response()->json([
            'status'      => $result['success'],
            'message'     => $result['success'] ? 'Consulta realizada' : $result['error'],
            'estado_baja' => $result['estado_baja'] ?? $venta->estado_baja,
            'codigo_sunat' => $result['codigo'] ?? null,
            'mensaje_sunat' => $result['mensaje'] ?? null,
        ]);
    }
}
