<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = [
        'orden_id',
        'caja_id',
        'mesa_id',
        'cliente_id',
        'mozo_id',
        'tipo_consumo',
        'base_imponible',
        'igv',
        'propina',
        'descuento',
        'total',
        'metodo_pago',
        'monto_recibido',
        'vuelto',
        'pagos_detalle',
        'notas',
        'activo',
        // Facturación electrónica
        'tipo_comprobante',
        'serie_comprobante',
        'numero_comprobante',
        'filename_comprobante',
        'estado_sunat',
        'error_comprobante',
        // Anulación
        'motivo_anulacion',
        'anulado_en',
        'tipo_anulacion',
        'filename_anulacion',
        // Baja SUNAT (RA/RC)
        'ticket_baja',
        'fecha_baja',
        'correlativo_baja',
        'estado_baja',
    ];

    protected $casts = [
        'pagos_detalle' => 'array',
    ];

    public function orden()
    {
        return $this->belongsTo(Orden::class);
    }
    public function mesa()
    {
        return $this->belongsTo(Mesa::class);
    }
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
    public function mozo()
    {
        return $this->belongsTo(Mozo::class);
    }
    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }
}
