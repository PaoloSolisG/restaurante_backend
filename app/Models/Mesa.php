<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    protected $table = 'mesas';

    protected $fillable = [
        'numero',
        'codigo',
        'estado',
        'capacidad',
        'activo'
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($mesa) {
            if (!$mesa->codigo) {
                $mesa->codigo = strtoupper(bin2hex(random_bytes(4)));
                // genera algo como "A9F4C1D2"
            }
        });
    }
}
