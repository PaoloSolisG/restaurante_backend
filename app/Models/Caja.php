<?php

namespace App\Models;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    protected $table = 'cajas';

    protected $fillable = [
        'usuario_apertura_id',
        'usuario_cierre_id',
        'monto_inicial',
        'monto_esperado',
        'monto_real',
        'diferencia',
        'total_efectivo',
        'total_tarjeta',
        'total_yape',
        'total_plin',
        'total_deposito',
        'total_mixto',
        'total_ventas',
        'estado',
        'apertura_at',
        'cierre_at',
        'notas_apertura',
        'notas_cierre'
    ];

    protected $casts = [
        'apertura_at' => 'datetime',
        'cierre_at'   => 'datetime',
    ];

    public function usuarioApertura()
    {
        return $this->belongsTo(Usuario::class, 'usuario_apertura_id');
    }
    public function usuarioCierre()
    {
        return $this->belongsTo(Usuario::class, 'usuario_cierre_id');
    }
    public function movimientos()
    {
        return $this->hasMany(CajaMovimiento::class);
    }
    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
}
