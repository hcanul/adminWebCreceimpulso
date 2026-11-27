<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->uuid('rate_id')->nullable()->change();
            $table->enum('disbursement_method', ['cash_branch', 'bank_deposit', 'bank_transfer'])->nullable()->change();
            $table->enum('repayment_method', ['cash_branch', 'bank_deposit', 'bank_transfer'])->nullable()->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Los créditos sin tasa inicial y las transferencias requieren una reversión planificada.');
    }
};
