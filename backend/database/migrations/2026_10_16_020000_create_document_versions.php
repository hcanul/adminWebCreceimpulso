<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 20);
            $table->uuid('source_id');
            $table->string('kind', 30);
            $table->unsignedInteger('source_version');
            $table->unsignedInteger('template_version');
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->json('snapshot');
            $table->longText('html');
            $table->string('sha256', 64);
            $table->timestamp('created_at');
            $table->unique(['source_type', 'source_id', 'kind', 'source_version', 'template_version'], 'document_source_version_unique');
        });
        Schema::create('document_signed_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->string('path');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('bytes');
            $table->timestamp('created_at');
            $table->unique(['document_version_id', 'sha256']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Los documentos emitidos y firmados requieren conservación y reversión planificada.');
    }
};
