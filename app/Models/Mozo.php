<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mozo extends Model
{
    protected $table = 'mozos';

    protected $fillable = [
        'identificacion',
        'nombre',
        'apellido',
        'email',
        'telefono',
        'direccion',
        'activo'
    ];
}
