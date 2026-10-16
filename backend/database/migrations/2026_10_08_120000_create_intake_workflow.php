<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1);
        });
        Schema::create('team_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('team_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained()->restrictOnDelete();
            $table->string('role_in_group')->default('integrante');
            $table->timestamps();
            $table->unique(['team_id', 'client_id']);
        });
        Schema::create('client_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('team_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('borrador');
            $table->json('fields');
            $table->decimal('amount_requested', 10, 2)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'client_id', 'status']);
        });
        Schema::create('application_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained('client_applications')->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['application_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_revisions');
        Schema::dropIfExists('client_applications');
        Schema::dropIfExists('team_memberships');
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('version'));
    }
};
