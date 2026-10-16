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
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->onDelete('restrict');
            $table->foreignUuid('team_id')->constrained('teams')->onDelete('restrict');
            $table->foreignUuid('promoter_id')->constrained('users')->onDelete('restrict');
            $table->foreignUuid('rate_id')->constrained('rates')->onDelete('restrict');
            $table->decimal('total_requested', 12, 2)->default(0);
            $table->decimal('total_approved', 12, 2)->default(0);
            $table->enum('status', ['pendiente', 'aprobado', 'entregado', 'pagado', 'cancelado'])->default('pendiente');
            $table->date('pacted_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
