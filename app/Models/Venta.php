<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = [
        'orden_id', 'mesa_id', 'cliente_id', 'mozo_id',
        'tipo_consumo',
        'base_imponible', 'igv', 'propina', 'descuento', 'total',
        'metodo_pago', 'monto_recibido', 'vuelto', 'pagos_detalle',
        'notas', 'activo'
    ];

    protected $casts = [
        'pagos_detalle' => 'array'
    ];

    public function orden()    { return $this->belongsTo(Orden::class); }
    public function mesa()     { return $this->belongsTo(Mesa::class); }
    public function cliente()  { return $this->belongsTo(Cliente::class); }
    public function mozo()     { return $this->belongsTo(Mozo::class); }
    public function detalles() { return $this->hasMany(VentaDetalle::class); }
}