<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'password',
        'role_id'
    ];

    protected $hidden = [
        'password',
    ];

    protected $appends = ['rol', 'permisos'];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function getRolAttribute(): string
    {
        return $this->role?->nombre ?? 'sin_rol';
    }

    public function getPermisosAttribute(): array
    {
        return $this->role?->permisos ?? [];
    }

    public function tienePermiso(string $permiso): bool
    {
        return $this->role?->tienePermiso($permiso) ?? false;
    }
}
