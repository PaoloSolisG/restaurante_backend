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
            'nombre'  => 'required|string|max:120',
            'email'   => 'nullable|email|max:120',
            'plan'    => 'nullable|in:basic,premium',
            'dominio' => 'required|string|max:100|unique:domains,domain',
        ]);

        $id = Str::slug($request->nombre) . '-' . Str::random(6);

        $tenant = Tenant::create([
            'id'     => $id,
            'nombre' => $request->nombre,
            'email'  => $request->email,
            'plan'   => $request->plan ?? 'basic',
            'activo' => true,
        ]);

        $tenant->createDomain(['domain' => $request->dominio]);

        return response()->json([
            'status'  => true,
            'message' => "Tenant '{$tenant->nombre}' creado. DB: restaurante_{$id}",
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
            'nombre' => 'sometimes|string|max:120',
            'email'  => 'sometimes|nullable|email|max:120',
            'plan'   => 'sometimes|in:basic,premium',
            'activo' => 'sometimes|boolean',
        ]);

        $tenant->update($request->only(['nombre', 'email', 'plan', 'activo']));

        return response()->json(['status' => true, 'data' => $tenant->fresh('domains')]);
    }

    public function destroy($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete(); // triggers DeleteDatabase job via event

        return response()->json(['status' => true, 'message' => 'Tenant eliminado y base de datos borrada']);
    }

    public function migrar($id)
    {
        $tenant = Tenant::findOrFail($id);

        tenancy()->initialize($tenant);
        \Artisan::call('tenants:migrate', [
            '--tenants' => [$id],
            '--force'   => true,
        ]);
        tenancy()->end();

        return response()->json([
            'status'  => true,
            'message' => "Migraciones ejecutadas en restaurante_{$id}",
            'output'  => \Artisan::output(),
        ]);
    }
}
