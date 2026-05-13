<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MozoSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('mozos')->insertOrIgnore([
            ['id' => 1, 'identificacion' => '77674894', 'nombre' => 'Sebastian', 'apellido' => 'Carbajal',  'email' => 'sebas.perez@gmail.com',  'telefono' => '999888778', 'direccion' => 'Av. Siempre Viva 124',                   'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'identificacion' => '77670688', 'nombre' => 'Luigi',     'apellido' => 'Solis',     'email' => 'luigi_solis@gmail.com',   'telefono' => '927222781', 'direccion' => 'Halcones 208 - Bellavista Callao',     'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'identificacion' => '74859652', 'nombre' => 'Karina',    'apellido' => 'Rivera',    'email' => 'karinarivera@gmail.com',  'telefono' => '959585623', 'direccion' => 'Halcones 208 - Bellavista Callao',     'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'identificacion' => '74859651', 'nombre' => 'Thalia',    'apellido' => 'Tupez',     'email' => 'thalitupez@gmail.com',    'telefono' => '959585621', 'direccion' => 'Halcones 208 - Bellavista Callao',     'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'identificacion' => '41526523', 'nombre' => 'Janet',     'apellido' => 'Rivera',    'email' => 'janetrivera@gmail.com',   'telefono' => '959585611', 'direccion' => 'Halcones 208 - Bellavista Callao',     'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'identificacion' => '41526525', 'nombre' => 'Miguel',    'apellido' => 'Solis',     'email' => 'miguelsolis@gmail.com',   'telefono' => '959585612', 'direccion' => 'Halcones 208 - Bellavista Callao',     'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
