<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->string('descripcion', 200)->nullable();
            $table->json('permisos');
            $table->boolean('editable')->default(true);
            $table->timestamps();
        });

        // Insertar roles por defecto
        DB::table('roles')->insert([
            [
                'nombre'      => 'admin',
                'descripcion' => 'Administrador - acceso total al sistema',
                'permisos'    => json_encode(['*']),
                'editable'    => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'nombre'      => 'cajero',
                'descripcion' => 'Caja, ventas, comprobantes y clientes',
                'permisos'    => json_encode(['dashboard', 'caja', 'ventas', 'comprobantes', 'venta_directa', 'clientes']),
                'editable'    => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'nombre'      => 'mozo',
                'descripcion' => 'Atencion de mesas y venta directa',
                'permisos'    => json_encode(['dashboard', 'atencion_mesa', 'venta_directa']),
                'editable'    => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'nombre'      => 'cocina',
                'descripcion' => 'Solo pantalla de ordenes (KDS)',
                'permisos'    => json_encode(['dashboard', 'ordenes']),
                'editable'    => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'nombre'      => 'barra',
                'descripcion' => 'Solo pedidos de barra/bebidas en KDS',
                'permisos'    => json_encode(['dashboard', 'ordenes']),
                'editable'    => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
