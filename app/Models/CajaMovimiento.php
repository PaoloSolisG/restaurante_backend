<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaMovimiento extends Model
{
    protected $table = 'caja_movimientos';

    protected $fillable = [
        'caja_id',
        'usuario_id',
        'venta_id',
        'tipo',
        'concepto',
        'descripcion',
        'monto',
        'metodo_pago'
    ];

    public function caja()
    {
        return $this->belongsTo(Caja::class);
    }
    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }
}
