<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orden_id')->unique();
            $table->unsignedBigInteger('mesa_id')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('mozo_id')->nullable();

            $table->enum('tipo_consumo', ['mesa', 'llevar', 'delivery'])->default('mesa');

            // Montos
            $table->decimal('base_imponible', 10, 2)->default(0);
            $table->decimal('igv', 10, 2)->default(0);
            $table->decimal('propina', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // Pago
            $table->enum('metodo_pago', [
                'efectivo',
                'tarjeta',
                'yape',
                'plin',
                'deposito',
                'mixto'
            ])->default('efectivo');
            $table->decimal('monto_recibido', 10, 2)->default(0);
            $table->decimal('vuelto', 10, 2)->default(0);
            $table->json('pagos_detalle')->nullable(); // para pago mixto

            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('orden_id')->references('id')->on('ordenes');
            $table->foreign('mesa_id')->references('id')->on('mesas')->onDelete('set null');
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};