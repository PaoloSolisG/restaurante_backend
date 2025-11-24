<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orden extends Model
{
    protected $table = 'ordenes'; // 👈 IMPORTANTE

    protected $fillable = [
        'mesa_id',
        'cliente_id',
        'tipo_consumo',
        'estado',
        'notas',
        'subtotal',
        'total'
    ];

    public function mesa()
    {
        return $this->belongsTo(Mesa::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles()
    {
        return $this->hasMany(OrdenDetalle::class);
    }

    public function mozo()
    {
        return $this->belongsTo(Mozo::class);
    }
}
