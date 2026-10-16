<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_participations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Si borramos el crédito, borramos las participaciones (Cascade)
            $table->foreignUuid('loan_application_id')->constrained('loan_applications')->onDelete('cascade');
            // No podemos borrar un cliente si tiene historial crediticio (Restrict)
            $table->foreignUuid('client_id')->constrained('clients')->onDelete('restrict');
            $table->enum('role_in_group', ['presidenta', 'tesorera', 'integrante'])->default('integrante');
            $table->decimal('amount_requested', 10, 2)->default(0);
            $table->decimal('amount_approved', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_participations');
    }
};
