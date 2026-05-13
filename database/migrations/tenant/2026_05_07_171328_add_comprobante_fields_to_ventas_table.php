<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo_comprobante', 10)->nullable()->after('notas');
            $table->string('serie_comprobante', 10)->nullable()->after('tipo_comprobante');
            $table->string('numero_comprobante', 30)->nullable()->after('serie_comprobante');
            $table->string('filename_comprobante', 100)->nullable()->after('numero_comprobante');
            $table->string('estado_sunat', 30)->nullable()->after('filename_comprobante');
            $table->text('error_comprobante')->nullable()->after('estado_sunat');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_comprobante',
                'serie_comprobante',
                'numero_comprobante',
                'filename_comprobante',
                'estado_sunat',
                'error_comprobante',
            ]);
        });
    }
};
