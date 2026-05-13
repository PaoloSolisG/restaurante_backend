<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna role_id (nullable para migrar datos)
        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password');
        });

        // 2. Mapear enum antiguo -> role_id de la nueva tabla
        $roles = DB::table('roles')->pluck('id', 'nombre');

        DB::table('usuarios')->where('rol', 'admin')->update(['role_id' => $roles['admin'] ?? null]);
        DB::table('usuarios')->where('rol', 'cocina')->update(['role_id' => $roles['cocina'] ?? null]);
        DB::table('usuarios')->where('rol', 'cliente')->update(['role_id' => $roles['mozo'] ?? null]);

        // 3. Hacer not nullable y FK
        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable(false)->change();
            $table->foreign('role_id')->references('id')->on('roles');
        });

        // 4. Eliminar columna enum antigua
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('rol');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->enum('rol', ['cliente', 'cocina', 'admin'])->default('cliente')->after('password');
        });

        $roles = DB::table('roles')->pluck('id', 'nombre');
        $mozoId  = $roles['mozo']  ?? null;
        $adminId = $roles['admin'] ?? null;
        $cocinaId = $roles['cocina'] ?? null;

        if ($adminId) DB::table('usuarios')->where('role_id', $adminId)->update(['rol' => 'admin']);
        if ($cocinaId) DB::table('usuarios')->where('role_id', $cocinaId)->update(['rol' => 'cocina']);
        // mozo, cajero, barra -> vuelven a 'cliente'
        if ($mozoId) DB::table('usuarios')->where('role_id', $mozoId)->update(['rol' => 'cliente']);
        DB::table('usuarios')->whereNotNull('role_id')->whereNotIn('role_id', array_filter([$adminId, $cocinaId, $mozoId]))->update(['rol' => 'cliente']);

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
