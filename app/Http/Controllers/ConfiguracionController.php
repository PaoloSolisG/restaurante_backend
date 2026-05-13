<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function show()
    {
        $t    = tenant();
        $data = $t->data ?? [];

        return response()->json([
            'restaurante' => [
                'nombre' => $t->nombre,
                'email'  => $t->email,
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

        $t = tenant();

        if ($request->has('restaurante')) {
            $rest = $request->input('restaurante');
            if (isset($rest['nombre'])) $t->nombre = $rest['nombre'];
            if (isset($rest['email']))  $t->email  = $rest['email'];
        }

        if ($request->has('facturacion')) {
            $data = $t->data ?? [];
            $data['facturacion'] = array_merge($data['facturacion'] ?? [], $request->input('facturacion'));
            $t->data = $data;
        }

        $t->save();

        return response()->json(['message' => 'Configuración guardada correctamente']);
    }
}
