<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->unsignedBigInteger('mozo_id')->nullable()->after('cliente_id');
            $table->foreign('mozo_id')->references('id')->on('mozos');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->dropForeign(['mozo_id']);
            $table->dropColumn('mozo_id');
        });
    }
};
