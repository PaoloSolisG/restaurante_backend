<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_detalles', function (Blueprint $table) {
            $table->string('cliente_nombre', 100)->nullable()->after('subtotal');
            $table->text('notas_item')->nullable()->after('cliente_nombre');
        });

        Schema::table('ordenes', function (Blueprint $table) {
            $table->string('origen', 20)->default('mozo')->after('tipo_consumo');
        });
    }

    public function down(): void
    {
        Schema::table('orden_detalles', function (Blueprint $table) {
            $table->dropColumn(['cliente_nombre', 'notas_item']);
        });
        Schema::table('ordenes', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};
