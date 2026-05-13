<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CategoriaSeeder::class,
            ProductoSeeder::class,
            MesaSeeder::class,
            CocineroSeeder::class,
            MozoSeeder::class,
            ClienteSeeder::class,
        ]);

        $adminRoleId = DB::table('roles')->where('nombre', 'admin')->value('id') ?? 1;

        DB::table('usuarios')->insertOrIgnore([
            [
                'nombre'     => 'Admin',
                'apellido'   => 'Restaurante',
                'email'      => 'admin@restaurante.com',
                'password'   => Hash::make('admin123'),
                'role_id'    => $adminRoleId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
