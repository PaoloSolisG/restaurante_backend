<?php

namespace App\Services;

use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacturacionService
{
    private string $baseUrl;
    private string $token;
    private string $rucEmisor;
    private string $serieBoleta;
    private string $serieFactura;

    public function __construct()
    {
        $cfg = $this->tenantFacturacionConfig();

        $this->baseUrl      = rtrim($cfg['api_url']       ?? config('facturacion.base_url'), '/');
        $this->token        = $cfg['token']               ?? config('facturacion.token');
        $this->rucEmisor    = $cfg['ruc_emisor']          ?? config('facturacion.ruc_emisor');
        $this->serieBoleta  = $cfg['serie_boleta']        ?? config('facturacion.serie_boleta');
        $this->serieFactura = $cfg['serie_factura']       ?? config('facturacion.serie_factura');
    }

    private function tenantFacturacionConfig(): array
    {
        try {
            $tenantId = optional(\tenant())->getTenantKey();
            if (!$tenantId) {
                $req      = \app('request');
                $tenantId = $req->header('X-Tenant') ?? $req->get('tenant');
            }
            if (!$tenantId) {
                return [];
            }
            $raw  = DB::connection('central')
                ->table('tenants')
                ->where('id', $tenantId)
                ->value('data');
            $data = json_decode($raw ?? '{}', true);
            return $data['facturacion'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getConfig(): array
    {
        return [
            'ruc_emisor'   => $this->rucEmisor,
            'serie_boleta' => $this->serieBoleta,
            'serie_factura'=> $this->serieFactura,
        ];
    }

    /** Verifica conexión con la API de Naniva */
    public function status(): array
    {
        try {
            $response = $this->client()->get('/status');
            return ['ok' => $response->successful(), 'data' => $response->json()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Emite un comprobante electrónico para una venta.
     */
    public function emitir(Venta $venta): array
    {
        $venta->loadMissing(['cliente', 'detalles']);

        [$tipoDoc, $serie] = $this->resolverTipoYSerie($venta);
        $clienteData       = $this->buildClienteData($venta);

        $payload = [
            'tipo_documento' => $tipoDoc,
            'cabecera'       => [
                'FECHA_EMISION'          => $venta->created_at->format('Y-m-d'),
                'CLIENTE_NRO_DOCUMENTO'  => $clienteData['numero'],
                'CLIENTE_TIPO_IDENTIDAD' => $clienteData['tipo'],
                'CLIENTE_NOMBRE'         => $clienteData['nombre'],
                'CODIGO_MONEDA'          => 'PEN',
                'TOTAL_GRAVADAS'         => number_format((float) $venta->base_imponible, 2, '.', ''),
                'TOTAL_TRIBUTO_IGV'      => number_format((float) $venta->igv,            2, '.', ''),
                'TOTAL_VENTA'            => number_format((float) $venta->total,           2, '.', ''),
            ],
            'detalles' => $this->buildDetalles($venta),
        ];

        try {
            $response = $this->client()->post('/emitir', $payload);
            $body     = $response->json() ?? [];

            // Log completo para diagnóstico — ver storage/logs/laravel.log
            Log::info('Naniva emitir response', [
                'venta_id'   => $venta->id,
                'http_status'=> $response->status(),
                'body'       => $body,
            ]);

            // Extraer numero SIN IMPORTAR si success=true o false.
            // Naniva asigna el correlativo antes de enviarlo a SUNAT,
            // por lo que puede estar en la respuesta aunque SUNAT rechace.
            $data     = is_array($body['data'] ?? null) ? $body['data'] : [];
            $numero   = $data['numero']   ?? null;
            $filename = $data['filename'] ?? ($numero ? "{$this->rucEmisor}-{$tipoDoc}-{$numero}" : null);
            $estado   = $data['estado']   ?? null;
            $errorMsg = $body['message']  ?? ($data['error'] ?? null);

            // Si Naniva asignó número → guardarlo siempre y marcar como enviado.
            // El estado real de SUNAT (Aceptado/Rechazado) se refleja en $estado.
            if ($numero) {
                $esAceptado = $response->successful() && ($body['success'] ?? false);
                return [
                    'success'            => true,   // tiene correlativo, fue enviado a Naniva
                    'tipo_comprobante'   => $tipoDoc,
                    'serie_comprobante'  => $serie,
                    'numero_comprobante' => $numero,
                    'filename'           => $filename,
                    'estado_sunat'       => $estado ?? ($esAceptado ? 'Procesado' : 'Enviado'),
                    'error'              => $esAceptado ? null : $errorMsg,
                ];
            }

            // Naniva no devolvió el número en la respuesta (data: null al rechazar).
            // Intentar recuperarlo consultando los comprobantes recientes de Naniva.
            Log::warning('Naniva sin numero en respuesta — intentando recuperación automática', [
                'venta_id' => $venta->id,
                'body'     => $body,
            ]);

            $recuperado = $this->recuperarCorrelativoReciente($tipoDoc, $serie);
            if ($recuperado) {
                $nRec = $recuperado['numero'];
                $fRec = $recuperado['filename'] ?? "{$this->rucEmisor}-{$tipoDoc}-{$nRec}";
                $eRec = $recuperado['estado'];
                Log::info('Naniva correlativo recuperado por GET /comprobantes', [
                    'venta_id' => $venta->id,
                    'numero'   => $nRec,
                    'estado'   => $eRec,
                ]);
                return [
                    'success'            => true,
                    'tipo_comprobante'   => $tipoDoc,
                    'serie_comprobante'  => $serie,
                    'numero_comprobante' => $nRec,
                    'filename'           => $fRec,
                    'estado_sunat'       => $eRec,
                    'error'              => $errorMsg,
                ];
            }

            // No se pudo recuperar el número de ninguna forma.
            Log::error('Naniva sin numero — recuperación fallida', [
                'venta_id' => $venta->id,
                'body'     => $body,
            ]);
            return [
                'success' => false,
                'error'   => $errorMsg ?? 'Naniva no procesó el comprobante',
            ];

        } catch (\Throwable $e) {
            Log::error('Naniva excepción/timeout', [
                'venta_id' => $venta->id,
                'error'    => $e->getMessage(),
                'class'    => get_class($e),
            ]);
            return ['success' => false, 'error' => 'Servicio de facturación no disponible'];
        }
    }

    /**
     * Descarga el PDF de un comprobante como base64.
     */
    public function getPdf(string $filename): array
    {
        try {
            $response = $this->downloadClient()->get("/comprobantes/{$filename}/pdf", [
                'mode' => 'base64',
            ]);

            if ($response->successful()) {
                $body = $response->json();
                if ($body && !empty($body['data']['pdf_base64'])) {
                    return ['success' => true, 'pdf_base64' => $body['data']['pdf_base64']];
                }
                return ['success' => true, 'pdf_base64' => base64_encode($response->body())];
            }

            return ['success' => false, 'error' => 'No se pudo obtener el PDF — status: ' . $response->status()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }


    /**
     * Reenvía un comprobante a SUNAT.
     */
    public function reenviar(string|int $comprobanteId): array
    {
        try {
            $response = $this->client()->post("/comprobantes/{$comprobanteId}/enviar");
            $body     = $response->json();

            return [
                'success' => $response->successful() && ($body['success'] ?? false),
                'data'    => $body,
                'error'   => $response->successful() ? null : ($body['message'] ?? 'Error al reenviar'),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }


    public function getXml(string $filename): array
    {
        try {
            $response = $this->downloadClient()->get("/comprobantes/{$filename}/xml");
            if ($response->successful()) {
                $body = $response->body();
                $json = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($json['data']['content'])) {
                    $body = $json['data']['content'];
                }
                return ['success' => true, 'xml' => $body];
            }
            return ['success' => false, 'error' => 'HTTP ' . $response->status() . ': ' . substr($response->body(), 0, 200)];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getCdr(string $filename): array
    {
        try {
            $response = $this->downloadClient()->get("/comprobantes/{$filename}/cdr");
            if ($response->successful()) {
                $body = $response->body();
                $json = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($json['data']['content'])) {
                    $body = $json['data']['content'];
                }
                return ['success' => true, 'cdr_base64' => base64_encode($body)];
            }
            return ['success' => false, 'error' => 'HTTP ' . $response->status() . ': ' . substr($response->body(), 0, 200)];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function consultarEstado(string $filename): array
    {
        try {
            $response = $this->client()->post('/consultar-estado', ['filename' => $filename]);
            $body = $response->json();
            return [
                'success' => $response->successful() && ($body['success'] ?? false),
                'data'    => $body['data'] ?? null,
                'error'   => $response->successful() ? null : ($body['message'] ?? 'Error al consultar'),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }


    /**
     * Dar de baja un comprobante ante SUNAT.
     * - Factura (01): genera RA (Comunicación de Baja / VoidedDocuments)
     * - Boleta  (03): genera RC (Resumen de Comprobantes con ESTADO_ITEM=3)
     */
    public function darBaja(Venta $venta, string $motivo): array
    {
        if (!$venta->tipo_comprobante || !$venta->serie_comprobante || !$venta->numero_comprobante) {
            return ['success' => false, 'error' => 'La venta no tiene comprobante emitido para dar de baja'];
        }

        $venta->loadMissing(['cliente', 'detalles']);

        // Extraer solo el correlativo numérico (ej: "B001-2" → "2")
        $partes = explode('-', $venta->numero_comprobante);
        $correlativoNum = end($partes);

        $esFactura = $venta->tipo_comprobante === '01';

        $payload = $esFactura
            ? $this->buildPayloadRA($venta, $correlativoNum, $motivo)
            : $this->buildPayloadRC($venta, $motivo);

        try {
            $response = $this->client()->post('/dar-baja', $payload);
            $body     = $response->json();

            Log::info('Naniva dar-baja', [
                'venta_id'  => $venta->id,
                'tipo'      => $esFactura ? 'RA' : 'RC',
                'status'    => $response->status(),
                'body'      => $body,
            ]);

            if ($response->successful() && ($body['success'] ?? false)) {
                $sunat       = $body['data']['sunat'] ?? [];
                $fechaBaja   = now()->format('Y-m-d');
                return [
                    'success'          => true,
                    'tipo_baja'        => $esFactura ? 'RA' : 'RC',
                    'mensaje'          => $body['message'] ?? 'Comprobante dado de baja correctamente',
                    'sunat'            => $sunat,
                    'ticket'           => $sunat['TICKET'] ?? null,
                    'fecha_baja'       => $fechaBaja,
                    'correlativo_baja' => '1',
                    'estado_baja'      => ($sunat['CODIGO'] ?? '') === '0' ? 'Pendiente' : 'Procesado',
                ];
            }

            $errorMsg = $body['message'] ?? 'Error desconocido al dar de baja';
            Log::error('Naniva dar-baja fallido', ['venta_id' => $venta->id, 'payload' => $payload, 'response' => $body]);
            return ['success' => false, 'error' => $errorMsg];

        } catch (\Throwable $e) {
            Log::error('Naniva dar-baja excepción', ['venta_id' => $venta->id, 'msg' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consulta el estado de un ticket de baja (RA/RC) ante SUNAT.
     * Endpoint Naniva: POST /consultar-estado
     */
    public function consultarTicketBaja(string $ticket, string $fechaBaja, string $correlativoBaja, bool $isAnulado = false): array
    {
        try {
            $payload = [
                'ticket'     => $ticket,
                'is_anulado' => $isAnulado,
                'cabecera'   => [
                    'FECHA_EMISION' => $fechaBaja,
                    'CORRELATIVO'   => $correlativoBaja,
                ],
            ];

            $response = $this->client()->post('/consultar-estado', $payload);
            $body     = $response->json();

            Log::info('Naniva consultar-ticket', ['ticket' => $ticket, 'status' => $response->status(), 'body' => $body]);

            if ($response->successful() && ($body['success'] ?? false)) {
                $sunat     = $body['data']['sunat'] ?? [];
                $codigo    = $sunat['CODIGO'] ?? '';
                // Código 0 = aceptado, 9999 = sin respuesta aún (beta/pendiente)
                $estadoBaja = match(true) {
                    $codigo === '0'    => 'Aceptado',
                    $codigo === '9999' => 'Pendiente',
                    default            => 'Rechazado',
                };

                return [
                    'success'    => true,
                    'estado_baja' => $estadoBaja,
                    'codigo'     => $codigo,
                    'mensaje'    => $sunat['MENSAJE'] ?? '',
                    'hash_cdr'   => $sunat['HASH_CDR'] ?? null,
                ];
            }

            return ['success' => false, 'error' => $body['message'] ?? 'Error al consultar ticket'];
        } catch (\Throwable $e) {
            Log::error('Naniva consultar-ticket excepción', ['ticket' => $ticket, 'msg' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildPayloadRA(Venta $venta, string $correlativoNum, string $motivo): array
    {
        return [
            'cabecera' => [
                'FECHA_EMISION'    => now()->format('Y-m-d'),
                'FECHA_REFERENCIA' => $venta->created_at->format('Y-m-d'),
                'NUMERO_DOCUMENTO' => '1',
            ],
            'detalles' => [[
                'TIPO_DOCUMENTO'        => '01',
                'DOCUMENTO_BAJA_SERIE'  => $venta->serie_comprobante,
                'DOCUMENTO_BAJA_NUMERO' => $correlativoNum,
                'BAJA_DESCRIPCION'      => strtoupper($motivo),
            ]],
        ];
    }

    private function buildPayloadRC(Venta $venta, string $motivo): array
    {
        $cliente = $venta->cliente;
        $clienteNombre  = $cliente ? strtoupper(trim($cliente->nombre . ' ' . ($cliente->apellido ?? ''))) : 'ANONIMO';
        $clienteNumero  = $cliente?->identificador ?? '11111111';
        $clienteTipo    = $cliente ? (['DNI' => '1', 'RUC' => '6'][$cliente->tipo_identificador] ?? '1') : '1';

        return [
            'cabecera' => [
                'FECHA_EMISION'    => now()->format('Y-m-d'),
                'FECHA_REFERENCIA' => $venta->created_at->format('Y-m-d'),
                'CORRELATIVO'      => '1',
            ],
            'detalles' => [[
                'TIPO_DOCUMENTO'         => '03',
                'NUMERO_DOCUMENTO'       => $venta->numero_comprobante,
                'ESTADO_ITEM'            => '3',
                'CLIENTE_NOMBRE'         => $clienteNombre,
                'CLIENTE_NRO_DOCUMENTO'  => $clienteNumero,
                'CLIENTE_TIPO_IDENTIDAD' => $clienteTipo,
                'CODIGO_MONEDA'          => 'PEN',
                'TOTAL_VENTA'            => number_format((float) $venta->total,           2, '.', ''),
                'TOTAL_GRAVADAS'         => number_format((float) $venta->base_imponible,  2, '.', ''),
                'TOTAL_TRIBUTO_IGV'      => number_format((float) $venta->igv,             2, '.', ''),
            ]],
        ];
    }

    /**
     * Emite una Nota de Crédito (07) que anula legalmente el comprobante original.
     * Genera un nuevo documento electrónico con su propio correlativo.
     */
    public function emitirNotaCredito(Venta $venta, string $motivo): array
    {
        if (!$venta->tipo_comprobante || !$venta->serie_comprobante || !$venta->numero_comprobante) {
            return ['success' => false, 'error' => 'La venta no tiene comprobante original para referenciar'];
        }

        $venta->loadMissing(['cliente', 'detalles']);

        // La NC usa la misma serie que el documento original
        $serieNC = $venta->serie_comprobante;
        $clienteData = $this->buildClienteData($venta);

        $payload = [
            'tipo_documento' => '07',
            'documento_referencia' => [
                'tipo'   => $venta->tipo_comprobante,
                'serie'  => $venta->serie_comprobante,
                'numero' => $venta->numero_comprobante,
            ],
            'motivo_nota' => $motivo,
            'cabecera' => [
                'FECHA_EMISION'          => now()->format('Y-m-d'),
                'CLIENTE_NRO_DOCUMENTO'  => $clienteData['numero'],
                'CLIENTE_TIPO_IDENTIDAD' => $clienteData['tipo'],
                'CLIENTE_NOMBRE'         => $clienteData['nombre'],
                'CODIGO_MONEDA'          => 'PEN',
                'TOTAL_GRAVADAS'         => number_format((float) $venta->base_imponible, 2, '.', ''),
                'TOTAL_TRIBUTO_IGV'      => number_format((float) $venta->igv,            2, '.', ''),
                'TOTAL_VENTA'            => number_format((float) $venta->total,           2, '.', ''),
            ],
            'detalles' => $this->buildDetalles($venta),
        ];

        try {
            $response = $this->client()->post('/emitir', $payload);
            $body = $response->json();

            Log::info('Naniva NC response', ['venta_id' => $venta->id, 'status' => $response->status(), 'body' => $body]);

            if ($response->successful() && ($body['success'] ?? false)) {
                return [
                    'success'  => true,
                    'numero'   => $body['data']['numero']   ?? null,
                    'filename' => $body['data']['filename']
                        ?? ($body['data']['numero']
                            ? "{$this->rucEmisor}-07-{$body['data']['numero']}"
                            : null),
                    'estado'   => $body['data']['estado']   ?? null,
                ];
            }

            $errorMsg = $body['message'] ?? 'Error al emitir Nota de Crédito';
            Log::error('Naniva NC fallida', ['venta_id' => $venta->id, 'response' => $body]);
            return ['success' => false, 'error' => $errorMsg];
        } catch (\Throwable $e) {
            Log::error('Naniva NC excepción', ['venta_id' => $venta->id, 'msg' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Consulta GET /comprobantes en Naniva para recuperar el último documento
     * del tipo/serie indicado. Retorna array con numero, filename y estado, o null.
     * Se usa cuando POST /emitir no devolvió el número en la respuesta.
     *
     * El campo de número en el listado de Naniva es "serie_numero" (ej: "F001-2"),
     * no "numero". El filename completo también viene en "filename".
     */
    private function recuperarCorrelativoReciente(string $tipoDoc, string $serie): ?array
    {
        try {
            $response = $this->client()->get('/comprobantes', [
                'tipo_documento' => $tipoDoc,
                'serie'          => $serie,
                'per_page'       => 1,
            ]);

            Log::info('Naniva GET /comprobantes (recovery)', [
                'tipo'   => $tipoDoc,
                'serie'  => $serie,
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            if (!$response->successful()) {
                return null;
            }

            $body = $response->json();

            // Soportar paginación Laravel ({ data: { data: [...] } }) y lista plana ({ data: [...] })
            $items = $body['data']['data'] ?? $body['data'] ?? [];

            if (!is_array($items) || empty($items)) {
                return null;
            }

            $latest = $items[0];

            // Naniva listado usa "serie_numero" (ej: "F001-2"), no "numero"
            $numero = $latest['serie_numero'] ?? $latest['numero'] ?? $latest['NUMERO'] ?? null;

            if (!$numero) {
                Log::warning('Naniva recovery: no se encontró campo numero en item', ['item' => $latest]);
                return null;
            }

            $createdAt = $latest['created_at'] ?? $latest['fecha_emision'] ?? null;

            // Solo usar si fue creado hace menos de 3 minutos (correlación temporal)
            if ($createdAt) {
                try {
                    $diff = now()->diffInSeconds(\Carbon\Carbon::parse($createdAt));
                    if ($diff > 180) {
                        Log::warning('Naniva recovery: comprobante demasiado antiguo', [
                            'created_at' => $createdAt,
                            'diff_s'     => $diff,
                        ]);
                        return null;
                    }
                } catch (\Throwable) {
                    // Si no parsea la fecha, usamos el número igual
                }
            }

            return [
                'numero'   => $numero,
                'filename' => $latest['filename'] ?? null,
                'estado'   => $latest['estado']   ?? 'Enviado',
            ];

        } catch (\Throwable $e) {
            Log::warning('Naniva recovery GET /comprobantes falló', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->withHeaders([
                'X-Emisor-RUC' => $this->rucEmisor,
                'Accept'       => 'application/json',
            ])
            ->asJson()
            ->timeout(90); // SUNAT puede tardar hasta 60-90s en responder
    }

    private function downloadClient()
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->timeout(15);
    }

    private function resolverTipoYSerie(Venta $venta): array
    {
        if ($venta->cliente && $venta->cliente->tipo_identificador === 'RUC') {
            return ['01', $this->serieFactura];
        }
        return ['03', $this->serieBoleta];
    }

    private function buildClienteData(Venta $venta): array
    {
        if (!$venta->cliente) {
            return ['tipo' => '0', 'numero' => '00000000', 'nombre' => 'CLIENTES VARIOS'];
        }

        $tipoMap = ['DNI' => '1', 'RUC' => '6'];

        return [
            'tipo'   => $tipoMap[$venta->cliente->tipo_identificador] ?? '1',
            'numero' => $venta->cliente->identificador,
            'nombre' => strtoupper(trim($venta->cliente->nombre . ' ' . ($venta->cliente->apellido ?? ''))),
        ];
    }

    private function buildDetalles(Venta $venta): array
    {
        return $venta->detalles->map(function ($detalle) {
            // precio_unitario incluye IGV 10.5% → extraemos base
            $precioBase = round((float) $detalle->precio_unitario / 1.105, 2);

            return [
                'CODIGO'           => $detalle->codigo_producto ?? 'PRD',
                'CANTIDAD'         => (string) $detalle->cantidad,
                'DESCRIPCION'      => $detalle->nombre_producto,
                'PRECIO_VALOR'     => number_format($precioBase, 2, '.', ''),
                'TIPO_TRIBUTO_IGV' => '10',
            ];
        })->toArray();
    }
}
