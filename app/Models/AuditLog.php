<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_nombre', 'accion', 'entidad',
        'entidad_id', 'descripcion', 'metadata', 'ip',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Registra una entrada de auditoría.
     *
     * @param string      $accion      Ej: 'venta.crear', 'caja.cerrar'
     * @param string|null $entidad     Ej: 'Venta', 'Caja', 'Orden'
     * @param int|null    $entidadId
     * @param string      $descripcion Texto legible para humanos
     * @param array       $metadata    Datos extra opcionales (totales, motivos, etc.)
     */
    public static function registrar(
        string  $accion,
        ?string $entidad,
        ?int    $entidadId,
        string  $descripcion,
        array   $metadata = []
    ): void {
        try {
            static::create([
                'user_id'     => Auth::id(),
                'user_nombre' => Auth::user()?->name ?? Auth::user()?->nombre ?? 'Sistema',
                'accion'      => $accion,
                'entidad'     => $entidad,
                'entidad_id'  => $entidadId,
                'descripcion' => $descripcion,
                'metadata'    => empty($metadata) ? null : $metadata,
                'ip'          => request()->ip(),
            ]);
        } catch (\Throwable) {
            // Los logs nunca deben romper el flujo principal
        }
    }
}
