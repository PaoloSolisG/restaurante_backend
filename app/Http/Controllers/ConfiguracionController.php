<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfiguracionController extends Controller
{
    private function centralConn(): string
    {
        return 'central';
    }

    public function show()
    {
        $t    = tenant();
        $row  = DB::connection($this->centralConn())
                    ->table('tenants')
                    ->where('id', $t->getTenantKey())
                    ->first();

        $data = json_decode($row->data ?? '{}', true) ?? [];

        return response()->json([
            'restaurante' => [
                'nombre' => $row->nombre ?? '',
                'email'  => $row->email  ?? '',
            ],
            'facturacion' => array_merge([
                'api_url'       => 'https://fe.naniva.cloud/api/v1',
                'token'         => '',
                'ruc_emisor'    => '',
                'razon_social'  => '',
                'direccion'     => '',
                'serie_boleta'  => 'B001',
                'serie_factura' => 'F001',
            ], $data['facturacion'] ?? []),
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

        $conn     = $this->centralConn();
        $tenantId = tenant()->getTenantKey();

        $row      = DB::connection($conn)->table('tenants')->where('id', $tenantId)->first();
        $data     = json_decode($row->data ?? '{}', true) ?? [];
        $updates  = [];

        if ($request->has('restaurante')) {
            $rest = $request->input('restaurante');
            if (isset($rest['nombre'])) $updates['nombre'] = $rest['nombre'];
            if (isset($rest['email']))  $updates['email']  = $rest['email'];
        }

        if ($request->has('facturacion')) {
            $data['facturacion'] = array_merge($data['facturacion'] ?? [], $request->input('facturacion'));
            $updates['data']     = json_encode($data);
        }

        if (!empty($updates)) {
            DB::connection($conn)->table('tenants')->where('id', $tenantId)->update($updates);
        }

        return response()->json(['message' => 'Configuración guardada correctamente']);
    }
}
