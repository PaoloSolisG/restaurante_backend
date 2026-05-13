<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

class InitializeTenancyOptional extends InitializeTenancyByRequestData
{
    protected function getPayload(Request $request): ?string
    {
        $payload = $request->header('X-Tenant')
            ?? $request->get('tenant')
            ?? env('DEFAULT_TENANT_ID');

        Log::error('InitializeTenancyOptional::getPayload', [
            'method'   => $request->method(),
            'uri'      => $request->path(),
            'header'   => $request->header('X-Tenant'),
            'default'  => env('DEFAULT_TENANT_ID'),
            'payload'  => $payload,
        ]);

        return $payload;
    }
}
