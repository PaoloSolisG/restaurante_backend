<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('productos')->insertOrIgnore([
            // Bebidas (id_categoria=1)
            ['id' =>  1, 'codigo' => 'BEV000', 'nombre' => 'Lomo Saltado',          'descripcion' => null, 'id_categoria' => 1,  'precio' => 10.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  4, 'codigo' => 'BEV003', 'nombre' => 'Limonada Clásica',      'descripcion' => null, 'id_categoria' => 1,  'precio' =>  5.50, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  5, 'codigo' => 'BEV004', 'nombre' => 'Maracuyá Frozen',       'descripcion' => null, 'id_categoria' => 1,  'precio' =>  7.50, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  6, 'codigo' => 'BEV005', 'nombre' => 'Inca Kola 500ml',       'descripcion' => null, 'id_categoria' => 1,  'precio' =>  5.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  7, 'codigo' => 'BEV006', 'nombre' => 'Agua Mineral 600ml',    'descripcion' => null, 'id_categoria' => 1,  'precio' =>  3.50, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            // Entradas (id_categoria=3)
            ['id' =>  8, 'codigo' => 'ENT001', 'nombre' => 'Papa a la Huancaína',   'descripcion' => null, 'id_categoria' => 3,  'precio' => 12.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  9, 'codigo' => 'ENT002', 'nombre' => 'Causa Limeña',          'descripcion' => null, 'id_categoria' => 3,  'precio' => 14.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'codigo' => 'ENT003', 'nombre' => 'Anticuchos de Corazón', 'descripcion' => null, 'id_categoria' => 3,  'precio' => 18.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'codigo' => 'ENT004', 'nombre' => 'Ocopa Arequipeña',      'descripcion' => null, 'id_categoria' => 3,  'precio' => 13.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'codigo' => 'ENT005', 'nombre' => 'Choritos a la Chalaca', 'descripcion' => null, 'id_categoria' => 3,  'precio' => 15.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            // Postres (id_categoria=4)
            ['id' => 13, 'codigo' => 'POS001', 'nombre' => 'Mazamorra Morada',      'descripcion' => null, 'id_categoria' => 4,  'precio' =>  6.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'codigo' => 'POS002', 'nombre' => 'Arroz con Leche',       'descripcion' => null, 'id_categoria' => 4,  'precio' =>  6.50, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 15, 'codigo' => 'POS003', 'nombre' => 'Suspiro a la Limeña',   'descripcion' => null, 'id_categoria' => 4,  'precio' => 10.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'codigo' => 'POS004', 'nombre' => 'Picarones',             'descripcion' => null, 'id_categoria' => 4,  'precio' =>  8.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'codigo' => 'POS005', 'nombre' => 'Torta de Chocolate',    'descripcion' => null, 'id_categoria' => 4,  'precio' => 12.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            // Fondos (id_categoria=5)
            ['id' => 18, 'codigo' => 'FON001', 'nombre' => 'Lomo Saltado',          'descripcion' => null, 'id_categoria' => 5,  'precio' => 28.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 19, 'codigo' => 'FON002', 'nombre' => 'Ají de Gallina',        'descripcion' => null, 'id_categoria' => 5,  'precio' => 22.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'codigo' => 'FON003', 'nombre' => 'Seco de Res con Frejoles','descripcion' => null,'id_categoria' => 5, 'precio' => 26.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'codigo' => 'FON004', 'nombre' => 'Pollo a la Brasa (1/4)','descripcion' => null, 'id_categoria' => 5,  'precio' => 20.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'codigo' => 'FON005', 'nombre' => 'Arroz Chaufa de Pollo', 'descripcion' => null, 'id_categoria' => 5,  'precio' => 18.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            // Sopas (id_categoria=6)
            ['id' => 23, 'codigo' => 'SOP001', 'nombre' => 'Sopa a la Minuta',      'descripcion' => null, 'id_categoria' => 6,  'precio' => 12.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 24, 'codigo' => 'SOP002', 'nombre' => 'Caldo de Gallina',      'descripcion' => null, 'id_categoria' => 6,  'precio' => 16.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 25, 'codigo' => 'SOP003', 'nombre' => 'Sancochado',            'descripcion' => null, 'id_categoria' => 6,  'precio' => 20.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 26, 'codigo' => 'SOP004', 'nombre' => 'Chupe de Camarones',    'descripcion' => null, 'id_categoria' => 6,  'precio' => 28.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 27, 'codigo' => 'SOP005', 'nombre' => 'Caldo Blanco Ayacuchano','descripcion' => null,'id_categoria' => 6,  'precio' => 18.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            // Mariscos (id_categoria=7)
            ['id' => 28, 'codigo' => 'MAR001', 'nombre' => 'Ceviche de Pescado',    'descripcion' => null, 'id_categoria' => 7,  'precio' => 22.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 29, 'codigo' => 'MAR002', 'nombre' => 'Arroz con Mariscos',    'descripcion' => null, 'id_categoria' => 7,  'precio' => 26.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 30, 'codigo' => 'MAR003', 'nombre' => 'Chicharrón de Calamar', 'descripcion' => null, 'id_categoria' => 7,  'precio' => 20.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 31, 'codigo' => 'MAR004', 'nombre' => 'Sudado de Pescado',     'descripcion' => null, 'id_categoria' => 7,  'precio' => 24.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 32, 'codigo' => 'MAR005', 'nombre' => 'Jalea Mixta',           'descripcion' => null, 'id_categoria' => 7,  'precio' => 30.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            // Parrillas (id_categoria=8)
            ['id' => 33, 'codigo' => 'PAR001', 'nombre' => 'Parrilla Personal',     'descripcion' => null, 'id_categoria' => 8,  'precio' => 32.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 34, 'codigo' => 'PAR002', 'nombre' => 'Carne Tomahwak',        'descripcion' => null, 'id_categoria' => 8,  'precio' => 10.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 35, 'codigo' => 'PAR003', 'nombre' => 'Pollo a la Parrilla',   'descripcion' => null, 'id_categoria' => 8,  'precio' => 24.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 36, 'codigo' => 'PAR004', 'nombre' => 'Costillas BBQ',         'descripcion' => null, 'id_categoria' => 8,  'precio' => 36.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 37, 'codigo' => 'PAR005', 'nombre' => 'Chorizo Parrillero',    'descripcion' => null, 'id_categoria' => 8,  'precio' => 15.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            // Ensaladas (id_categoria=9)
            ['id' => 38, 'codigo' => 'ENS001', 'nombre' => 'Ensalada César',        'descripcion' => null, 'id_categoria' => 9,  'precio' => 22.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 39, 'codigo' => 'ENS002', 'nombre' => 'Ensalada Caprese',      'descripcion' => null, 'id_categoria' => 9,  'precio' => 18.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 40, 'codigo' => 'ENS003', 'nombre' => 'Ensalada de Quinoa',    'descripcion' => null, 'id_categoria' => 9,  'precio' => 20.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 41, 'codigo' => 'ENS004', 'nombre' => 'Ensalada Tropical',     'descripcion' => null, 'id_categoria' => 9,  'precio' => 23.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 42, 'codigo' => 'ENS005', 'nombre' => 'Ensalada Mixta',        'descripcion' => null, 'id_categoria' => 9,  'precio' => 15.00, 'imagen' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
