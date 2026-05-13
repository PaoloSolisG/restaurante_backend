<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('ticket_baja', 50)->nullable()->after('filename_anulacion')
                  ->comment('Ticket SUNAT retornado por dar-baja (RA/RC)');
            $table->string('fecha_baja', 10)->nullable()->after('ticket_baja')
                  ->comment('Fecha YYYY-MM-DD de emisión del RA/RC');
            $table->string('correlativo_baja', 10)->nullable()->after('fecha_baja')
                  ->comment('Correlativo del RA/RC (siempre "1" por ahora)');
            $table->string('estado_baja', 30)->nullable()->after('correlativo_baja')
                  ->comment('Estado final de la baja: Pendiente, Aceptado, Rechazado');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['ticket_baja', 'fecha_baja', 'correlativo_baja', 'estado_baja']);
        });
    }
};
