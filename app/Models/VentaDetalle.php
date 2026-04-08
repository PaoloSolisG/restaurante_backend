<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaDetalle extends Model
{
    protected $table = 'venta_detalles';

    protected $fillable = [
        'venta_id', 'producto_id',
        'nombre_producto', 'codigo_producto',
        'precio_unitario', 'cantidad', 'subtotal'
    ];

    public function venta()    { return $this->belongsTo(Venta::class); }
    public function producto() { return $this->belongsTo(Producto::class); }
}