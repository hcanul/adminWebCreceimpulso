<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('city')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('branches')->whereNull('city')->exists()) {
            throw new RuntimeException('Completa las localidades antes de restaurar la restricción NOT NULL.');
        }
        Schema::table('branches', function (Blueprint $table) {
            $table->string('city')->nullable(false)->change();
        });
    }
};
