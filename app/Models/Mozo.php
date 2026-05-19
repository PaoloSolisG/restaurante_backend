<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mozo extends Model
{
    protected $table = 'mozos';

    protected $fillable = [
        'usuario_id',
        'identificacion',
        'nombre',
        'apellido',
        'email',
        'telefono',
        'direccion',
        'activo',
    ];

    // El usuario del sistema vinculado a este mozo
    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    // Órdenes asignadas a este mozo
    public function ordenes()
    {
        return $this->hasMany(Orden::class);
    }
}
