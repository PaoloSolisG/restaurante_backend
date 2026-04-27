<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caja_id');
            $table->unsignedBigInteger('usuario_id');         // quien registró el movimiento
            $table->unsignedBigInteger('venta_id')->nullable(); // si es ingreso por venta

            $table->enum('tipo', ['ingreso', 'egreso']);
            $table->enum('concepto', [
                'venta',        // ingreso automático al registrar venta
                'ingreso_extra', // ingreso manual (ej: adelanto)
                'retiro',       // retiro de efectivo
                'gasto',        // gasto operativo (ej: compra de insumos)
                'ajuste',       // corrección
            ]);

            $table->string('descripcion')->nullable();
            $table->decimal('monto', 10, 2);
            $table->enum('metodo_pago', [
                'efectivo', 'tarjeta', 'yape', 'plin', 'deposito', 'mixto'
            ])->default('efectivo');

            $table->timestamps();

            $table->foreign('caja_id')->references('id')->on('cajas')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('users');
            $table->foreign('venta_id')->references('id')->on('ventas')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_movimientos');
    }
};