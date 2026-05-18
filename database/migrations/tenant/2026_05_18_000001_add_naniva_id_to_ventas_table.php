<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // ID numérico interno de Naniva — necesario para el endpoint /comprobantes/{id}/enviar
            $table->unsignedBigInteger('naniva_id')->nullable()->after('filename_comprobante');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('naniva_id');
        });
    }
};
