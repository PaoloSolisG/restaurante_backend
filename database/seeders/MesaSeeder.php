<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MesaSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('mesas')->insertOrIgnore([
            ['id' => 1,  'numero' => 'Mesa 1',  'codigo' => '92C5092F', 'estado' => 'libre', 'capacidad' => 6, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2,  'numero' => 'Mesa 2',  'codigo' => '43A7FA84', 'estado' => 'libre', 'capacidad' => 4, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3,  'numero' => 'Mesa 3',  'codigo' => '15CBAD23', 'estado' => 'libre', 'capacidad' => 4, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4,  'numero' => 'Mesa 4',  'codigo' => '424D6D61', 'estado' => 'libre', 'capacidad' => 8, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5,  'numero' => 'Mesa 5',  'codigo' => '1BC1FF0F', 'estado' => 'libre', 'capacidad' => 4, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6,  'numero' => 'Mesa 6',  'codigo' => '0801FA2C', 'estado' => 'libre', 'capacidad' => 4, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7,  'numero' => 'Mesa 7',  'codigo' => 'F896C2F6', 'estado' => 'libre', 'capacidad' => 8, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8,  'numero' => 'Mesa 8',  'codigo' => '13198D8D', 'estado' => 'libre', 'capacidad' => 4, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9,  'numero' => 'Mesa 9',  'codigo' => 'B6F97F15', 'estado' => 'libre', 'capacidad' => 4, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'numero' => 'Mesa 10', 'codigo' => 'E2BDCB2B', 'estado' => 'libre', 'capacidad' => 4, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'numero' => 'Mesa 11', 'codigo' => '7AD3A245', 'estado' => 'libre', 'capacidad' => 4, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'numero' => 'Mesa 12', 'codigo' => '7C585551', 'estado' => 'libre', 'capacidad' => 4, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
