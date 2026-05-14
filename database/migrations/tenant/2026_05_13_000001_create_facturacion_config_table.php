<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturacion_config', function (Blueprint $table) {
            $table->id();
            $table->string('api_url')->default('https://fe.naniva.cloud/api/v1');
            $table->string('token', 500)->default('');
            $table->string('ruc_emisor', 20)->default('');
            $table->string('razon_social')->default('');
            $table->string('direccion')->default('');
            $table->string('serie_boleta', 10)->default('B001');
            $table->string('serie_factura', 10)->default('F001');
            $table->timestamps();
        });

        // Fila única de configuración
        DB::table('facturacion_config')->insert([
            'api_url'       => 'https://fe.naniva.cloud/api/v1',
            'token'         => '',
            'ruc_emisor'    => '',
            'razon_social'  => '',
            'direccion'     => '',
            'serie_boleta'  => 'B001',
            'serie_factura' => 'F001',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('facturacion_config');
    }
};
