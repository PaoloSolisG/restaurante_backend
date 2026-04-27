<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            // Eliminar las foreign keys incorrectas
            $table->dropForeign(['usuario_apertura_id']);
            $table->dropForeign(['usuario_cierre_id']);

            // Recrear apuntando a usuarios
            $table->foreign('usuario_apertura_id')->references('id')->on('usuarios');
            $table->foreign('usuario_cierre_id')->references('id')->on('usuarios')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropForeign(['usuario_apertura_id']);
            $table->dropForeign(['usuario_cierre_id']);
        });
    }
};
