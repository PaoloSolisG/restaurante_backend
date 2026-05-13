<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('clientes')->insertOrIgnore([
            ['id' => 1, 'tipo_identificador' => 'RUC', 'identificador' => '20547896321', 'nombre' => 'Inversiones La Molina',                   'apellido' => 'E.I.R.L.',           'email' => 'ventas@lamolina.pe',        'telefono' => '945123678', 'direccion' => 'Calle Las Fresias 125, La Molina',                         'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'tipo_identificador' => 'RUC', 'identificador' => '20538856674', 'nombre' => 'ARTROSCOPICTRAUMA S.A.C.',                'apellido' => 'Gomez',              'email' => 'paolosolisgomez1@gmail.com','telefono' => '958485962', 'direccion' => 'Calle Los Halcones Bellavista Callao',                'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'tipo_identificador' => 'DNI', 'identificador' => '77676921',    'nombre' => 'Romero',                                  'apellido' => 'Sanchez Sillau',     'email' => 'romero12@gmail.com',        'telefono' => '928761565', 'direccion' => 'Calle nueva esperanza 12 - Callao',                        'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'tipo_identificador' => 'DNI', 'identificador' => '77670689',    'nombre' => 'PAOLO FRANCIS',                           'apellido' => 'SOLIS GOMEZ',        'email' => 'paolosolis123@gmail.com',   'telefono' => '959564857', 'direccion' => 'calle halcones 2001',                                      'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'tipo_identificador' => 'DNI', 'identificador' => '77474821',    'nombre' => 'JORGE ERNESTO',                           'apellido' => 'RODRIGUEZ COLMENARES','email' => 'ernesto@gmail.com',        'telefono' => '954851254', 'direccion' => 'calle los sauces 209 - san juan de lurigancho',            'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'tipo_identificador' => 'RUC', 'identificador' => '20570892291', 'nombre' => '"ALFARO & CONTADORES" SOC. ANON. CERRADA', 'apellido' => null,               'email' => 'alfaro@gmail.com',          'telefono' => '959589596', 'direccion' => 'CAL. MARISCAL SUCRE NRO. 1513 SEC. PUEBLO NUEVO - CAJAMARCA', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'tipo_identificador' => 'DNI', 'identificador' => '77670681',    'nombre' => 'HELARD JOSEF',                            'apellido' => 'ALVAREZ QUILCA',     'email' => 'helard@gmail.com',          'telefono' => '958885632', 'direccion' => 'los rosales segunda etapa - ate',                          'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
