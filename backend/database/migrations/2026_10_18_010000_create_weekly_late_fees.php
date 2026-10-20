<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_late_fees', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('loan_application_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('loan_installment_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('client_id')->constrained()->restrictOnDelete();
            $t->string('policy', 80);
            $t->date('charged_on');
            $t->decimal('base', 12, 2);
            $t->decimal('amount', 12, 2);
            $t->timestamp('created_at');
            $t->unique(['loan_installment_id', 'charged_on']);
        });
        Schema::create('late_fee_payment_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('credit_payment_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('weekly_late_fee_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('payer_client_id')->constrained('clients')->restrictOnDelete();
            $t->decimal('amount', 12, 2);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La mora y sus aplicaciones requieren reversos auditados; no se eliminan.');
    }
};
