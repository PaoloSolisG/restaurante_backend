<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('categorias')->insertOrIgnore([
            ['id' => 1,  'nombre' => 'Bebidas',      'descripcion' => 'Bebidas',                                      'area' => 'cocina', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3,  'nombre' => 'Entradas',     'descripcion' => 'Todo tipo de entradas',                        'area' => 'cocina', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4,  'nombre' => 'Postres',      'descripcion' => 'Todo tipo de postres',                         'area' => 'cocina', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5,  'nombre' => 'Fondos',       'descripcion' => 'Platos de fondo',                              'area' => 'cocina', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6,  'nombre' => 'Sopas',        'descripcion' => 'Variedad de sopas peruanas',                   'area' => 'cocina', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7,  'nombre' => 'Mariscos',     'descripcion' => 'Platos preparados con pescados y mariscos',    'area' => 'cocina', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8,  'nombre' => 'Parrillas',    'descripcion' => 'Carnes y anticuchos a la parrilla',            'area' => 'cocina', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9,  'nombre' => 'Ensaladas',    'descripcion' => 'Ensaladas frescas y saludables',               'area' => 'cocina', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'nombre' => 'Hamburguesas', 'descripcion' => 'Hamburguesas',                                 'area' => 'cocina', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'nombre' => 'Helados',      'descripcion' => 'Helados',                                      'area' => 'cocina', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
