<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

class InitializeTenancyOptional extends InitializeTenancyByRequestData
{
    protected function getPayload(Request $request): ?string
    {
        return $request->header('X-Tenant')
            ?? $request->get('tenant')
            ?? (getenv('DEFAULT_TENANT_ID') ?: null);
    }
}
