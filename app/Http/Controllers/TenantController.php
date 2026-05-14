<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::with('domains')->latest()->get();
        return response()->json(['status' => true, 'data' => $tenants]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'                    => 'required|string|max:120',
            'email'                     => 'nullable|email|max:120',
            'plan'                      => 'nullable|in:basic,premium',
            'dominio'                   => 'required|string|max:100|unique:domains,domain',
            'facturacion.token'         => 'nullable|string|max:500',
            'facturacion.ruc_emisor'    => 'nullable|string|max:20',
            'facturacion.razon_social'  => 'nullable|string|max:255',
            'facturacion.direccion'     => 'nullable|string|max:500',
            'facturacion.serie_boleta'  => 'nullable|string|max:10',
            'facturacion.serie_factura' => 'nullable|string|max:10',
        ]);

        // Generar ID único basado en el nombre
        $baseId = Str::slug($request->nombre);
        $id     = $baseId;
        $i      = 2;
        while (Tenant::find($id)) {
            $id = $baseId . '-' . $i++;
        }

        // Preparar config de facturación
        $fc = $request->input('facturacion', []);
        $data = [];
        if (!empty($fc)) {
            $data['facturacion'] = array_filter([
                'api_url'       => 'https://fe.naniva.cloud/api/v1',
                'token'         => $fc['token']         ?? null,
                'ruc_emisor'    => $fc['ruc_emisor']    ?? null,
                'razon_social'  => $fc['razon_social']  ?? null,
                'direccion'     => $fc['direccion']     ?? null,
                'serie_boleta'  => $fc['serie_boleta']  ?? 'B001',
                'serie_factura' => $fc['serie_factura'] ?? 'F001',
            ], fn($v) => $v !== null && $v !== '');
        }

        $tenant = Tenant::create([
            'id'     => $id,
            'nombre' => $request->nombre,
            'email'  => $request->email,
            'plan'   => $request->plan ?? 'basic',
            'activo' => true,
            'data'   => $data ?: null,
        ]);

        $tenant->createDomain(['domain' => $request->dominio]);

        // Correr migraciones automáticamente
        tenancy()->initialize($tenant);
        \Artisan::call('tenants:migrate', ['--tenants' => [$id], '--force' => true]);
        tenancy()->end();

        return response()->json([
            'status'  => true,
            'message' => "Restaurante '{$tenant->nombre}' creado correctamente.",
            'data'    => $tenant->load('domains'),
        ], 201);
    }

    public function show($id)
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        return response()->json(['status' => true, 'data' => $tenant]);
    }

    public function update(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);

        $request->validate([
            'nombre'                    => 'sometimes|string|max:120',
            'email'                     => 'sometimes|nullable|email|max:120',
            'plan'                      => 'sometimes|in:basic,premium',
            'activo'                    => 'sometimes|boolean',
            'facturacion.token'         => 'sometimes|string|max:500',
            'facturacion.ruc_emisor'    => 'sometimes|string|max:20',
            'facturacion.razon_social'  => 'sometimes|string|max:255',
            'facturacion.direccion'     => 'sometimes|string|max:500',
            'facturacion.serie_boleta'  => 'sometimes|string|max:10',
            'facturacion.serie_factura' => 'sometimes|string|max:10',
        ]);

        $tenant->update($request->only(['nombre', 'email', 'plan', 'activo']));

        if ($request->has('facturacion')) {
            $data = $tenant->data ?? [];
            $data['facturacion'] = array_merge(
                $data['facturacion'] ?? [],
                array_filter($request->input('facturacion'), fn($v) => $v !== null)
            );
            $tenant->update(['data' => $data]);
        }

        return response()->json(['status' => true, 'data' => $tenant->fresh('domains')]);
    }

    public function destroy($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete();
        return response()->json(['status' => true, 'message' => 'Restaurante eliminado y base de datos borrada.']);
    }

    public function migrar($id)
    {
        $tenant = Tenant::findOrFail($id);

        tenancy()->initialize($tenant);
        \Artisan::call('tenants:migrate', ['--tenants' => [$id], '--force' => true]);
        tenancy()->end();

        return response()->json([
            'status'  => true,
            'message' => "Migraciones ejecutadas en restaurante_{$id}",
            'output'  => \Artisan::output(),
        ]);
    }
}
