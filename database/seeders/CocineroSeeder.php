<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CocineroSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('cocineros')->insertOrIgnore([
            ['id' => 1, 'nombre' => 'Juan', 'apellido' => 'Pérez', 'email' => 'juan@example.com', 'telefono' => '987654321', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
