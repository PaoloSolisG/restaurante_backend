<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('motivo_anulacion')->nullable()->after('error_comprobante');
            $table->timestamp('anulado_en')->nullable()->after('motivo_anulacion');
            $table->string('tipo_anulacion', 10)->nullable()->after('anulado_en')->comment('RA = Baja, NC = Nota de Crédito');
            $table->string('filename_anulacion', 100)->nullable()->after('tipo_anulacion');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['motivo_anulacion', 'anulado_en', 'tipo_anulacion', 'filename_anulacion']);
        });
    }
};
