<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_profiles', function (Blueprint $table) {
            $table->string('scope', 36)->primary();
            $table->unsignedInteger('version')->default(0);
            $table->boolean('use_general')->default(true);
            $table->json('fields');
            $table->uuid('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::table('document_profiles')->insert(['scope' => 'general', 'fields' => '{}', 'use_general' => true]);
    }

    public function down(): void
    {
        throw new RuntimeException('Los perfiles documentales requieren conservación de su historial.');
    }
};
