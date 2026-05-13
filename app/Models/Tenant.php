<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public static function getCustomColumns(): array
    {
        return ['id', 'nombre', 'email', 'plan', 'activo'];
    }

    protected $casts = [
        'activo' => 'boolean',
        'data'   => 'array',
    ];

    protected $fillable = ['id', 'nombre', 'email', 'plan', 'activo', 'data'];
}
