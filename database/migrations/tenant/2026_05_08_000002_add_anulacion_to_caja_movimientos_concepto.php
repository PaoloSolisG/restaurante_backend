<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE caja_movimientos MODIFY concepto ENUM('venta','ingreso_extra','retiro','gasto','ajuste','anulacion') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE caja_movimientos MODIFY concepto ENUM('venta','ingreso_extra','retiro','gasto','ajuste') NOT NULL");
    }
};
