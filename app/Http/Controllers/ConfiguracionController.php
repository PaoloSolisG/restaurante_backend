<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfiguracionController extends Controller
{
    public function show()
    {
        // Nombre/email del tenant vienen del DB central
        $t   = tenant();
        $row = DB::connection('central')
                    ->table('tenants')
                    ->where('id', $t->getTenantKey())
                    ->first();

        // Configuración de facturación viene del DB propio del tenant
        $fc = DB::table('facturacion_config')->first();

        return response()->json([
            'restaurante' => [
                'nombre' => $row->nombre ?? '',
                'email'  => $row->email  ?? '',
            ],
            'facturacion' => [
                'api_url'       => $fc->api_url       ?? 'https://fe.naniva.cloud/api/v1',
                'token'         => $fc->token          ?? '',
                'ruc_emisor'    => $fc->ruc_emisor     ?? '',
                'razon_social'  => $fc->razon_social   ?? '',
                'direccion'     => $fc->direccion      ?? '',
                'serie_boleta'  => $fc->serie_boleta   ?? 'B001',
                'serie_factura' => $fc->serie_factura  ?? 'F001',
            ],
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'restaurante.nombre'        => 'sometimes|string|max:255',
            'restaurante.email'         => 'sometimes|email|max:255',
            'facturacion.api_url'       => 'sometimes|url|max:500',
            'facturacion.token'         => 'sometimes|string|max:500',
            'facturacion.ruc_emisor'    => 'sometimes|string|max:20',
            'facturacion.razon_social'  => 'sometimes|string|max:255',
            'facturacion.direccion'     => 'sometimes|string|max:500',
            'facturacion.serie_boleta'  => 'sometimes|string|max:10',
            'facturacion.serie_factura' => 'sometimes|string|max:10',
        ]);

        // Actualizar nombre/email del tenant en DB central
        if ($request->has('restaurante')) {
            $rest    = $request->input('restaurante');
            $updates = array_filter([
                'nombre' => $rest['nombre'] ?? null,
                'email'  => $rest['email']  ?? null,
            ]);
            if ($updates) {
                DB::connection('central')
                    ->table('tenants')
                    ->where('id', tenant()->getTenantKey())
                    ->update($updates);
            }
        }

        // Actualizar facturación en DB propia del tenant
        if ($request->has('facturacion')) {
            $fc = $request->input('facturacion');
            DB::table('facturacion_config')->updateOrInsert(
                ['id' => 1],
                array_filter([
                    'api_url'       => $fc['api_url']       ?? null,
                    'token'         => $fc['token']          ?? null,
                    'ruc_emisor'    => $fc['ruc_emisor']     ?? null,
                    'razon_social'  => $fc['razon_social']   ?? null,
                    'direccion'     => $fc['direccion']      ?? null,
                    'serie_boleta'  => $fc['serie_boleta']   ?? null,
                    'serie_factura' => $fc['serie_factura']  ?? null,
                    'updated_at'    => now(),
                ], fn($v) => $v !== null)
            );
        }

        return response()->json(['message' => 'Configuración guardada correctamente']);
    }
}
