<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'nombre' => 'admin',   'descripcion' => 'Administrador - acceso total al sistema',      'permisos' => '["*"]',                                                                              'editable' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'nombre' => 'cajero',  'descripcion' => 'Caja, ventas, comprobantes y clientes',        'permisos' => '["dashboard","caja","ventas","comprobantes","venta_directa","clientes"]',             'editable' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'nombre' => 'mozo',    'descripcion' => 'Atencion de mesas y venta directa',            'permisos' => '["dashboard","atencion_mesa","venta_directa"]',                                       'editable' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'nombre' => 'cocina',  'descripcion' => 'Solo pantalla de ordenes (KDS)',               'permisos' => '["dashboard","ordenes"]',                                                             'editable' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'nombre' => 'barra',   'descripcion' => 'Solo pedidos de barra/bebidas en KDS',         'permisos' => '["dashboard","ordenes"]',                                                             'editable' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
