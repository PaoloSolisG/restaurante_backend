<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_nombre', 120)->nullable(); // snapshot — sin FK para preservar logs si se elimina el usuario
            $table->string('accion', 80);                  // ej: 'venta.crear', 'caja.abrir', 'comprobante.dar_baja'
            $table->string('entidad', 60)->nullable();     // ej: 'Venta', 'Caja', 'Orden'
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->string('descripcion');                 // texto legible para humanos
            $table->json('metadata')->nullable();          // datos extra (totales, métodos, motivos, etc.)
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['accion', 'created_at']);
            $table->index(['entidad', 'entidad_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
