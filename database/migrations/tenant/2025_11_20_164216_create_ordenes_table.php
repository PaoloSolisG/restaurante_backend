<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mesa_id');
            $table->unsignedBigInteger('cliente_id')->nullable();

            $table->enum('estado', [
                'pendiente',
                'en_preparacion',
                'listo',
                'entregado',
                'cerrado',
                'cancelado'
            ])->default('pendiente');

            $table->enum('tipo_consumo', ['mesa', 'llevar', 'delivery'])->default('mesa');

            $table->text('notas')->nullable();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->foreign('mesa_id')->references('id')->on('mesas');
            $table->foreign('cliente_id')->references('id')->on('clientes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes');
    }
};
