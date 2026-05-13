<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'nombre',
        'descripcion',
        'permisos',
        'editable',
    ];

    protected $casts = [
        'permisos' => 'array',
        'editable' => 'boolean',
    ];

    public function usuarios()
    {
        return $this->hasMany(Usuario::class);
    }

    public function tienePermiso(string $permiso): bool
    {
        $permisos = $this->permisos ?? [];
        return in_array('*', $permisos) || in_array($permiso, $permisos);
    }
}
