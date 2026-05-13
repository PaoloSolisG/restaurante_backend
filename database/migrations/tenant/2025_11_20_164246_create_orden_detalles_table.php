<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orden_id');
            $table->unsignedBigInteger('producto_id');

            $table->integer('cantidad')->default(1);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);

            $table->timestamps();

            $table->foreign('orden_id')
                ->references('id')->on('ordenes')
                ->onDelete('cascade');

            $table->foreign('producto_id')
                ->references('id')->on('productos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_detalles');
    }
};
