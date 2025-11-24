<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenDetalle extends Model
{
    protected $table = 'orden_detalles';

    protected $fillable = [
        'orden_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'cocinero_id',
        'area',
        'estado'
    ];


    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }


    // 👈 Agregar relación con Orden
    public function orden()
    {
        return $this->belongsTo(Orden::class, 'orden_id');
    }
}
