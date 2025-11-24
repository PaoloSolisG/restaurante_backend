<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('cocinero_id')->nullable()->after('producto_id');
            $table->enum('area', ['cocina', 'barra', 'postres'])->default('cocina')->after('cocinero_id');
            $table->enum('estado', ['pendiente', 'en_preparacion', 'listo', 'entregado'])->default('pendiente')->after('area');

            $table->foreign('cocinero_id')->references('id')->on('cocineros')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orden_detalles', function (Blueprint $table) {
            $table->dropForeign(['cocinero_id']);
            $table->dropColumn(['cocinero_id', 'area', 'estado']);
        });
    }
};
