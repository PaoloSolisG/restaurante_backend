<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_apertura_id');  // quien abrió
            $table->unsignedBigInteger('usuario_cierre_id')->nullable(); // quien cerró

            $table->decimal('monto_inicial', 10, 2)->default(0);   // efectivo al abrir
            $table->decimal('monto_esperado', 10, 2)->default(0);  // calculado: inicial + ingresos - egresos
            $table->decimal('monto_real', 10, 2)->nullable();      // lo que contó el cajero al cerrar
            $table->decimal('diferencia', 10, 2)->nullable();      // monto_real - monto_esperado

            // Totales por método de pago (se calculan al cerrar)
            $table->decimal('total_efectivo', 10, 2)->default(0);
            $table->decimal('total_tarjeta', 10, 2)->default(0);
            $table->decimal('total_yape', 10, 2)->default(0);
            $table->decimal('total_plin', 10, 2)->default(0);
            $table->decimal('total_deposito', 10, 2)->default(0);
            $table->decimal('total_mixto', 10, 2)->default(0);
            $table->decimal('total_ventas', 10, 2)->default(0);   // suma total del periodo

            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');

            $table->timestamp('apertura_at');
            $table->timestamp('cierre_at')->nullable();

            $table->text('notas_apertura')->nullable();
            $table->text('notas_cierre')->nullable();

            $table->timestamps();

            $table->foreign('usuario_apertura_id')->references('id')->on('users');
            $table->foreign('usuario_cierre_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
