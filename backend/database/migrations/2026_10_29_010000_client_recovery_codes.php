<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_activation_codes', function (Blueprint $table) {
            $table->string('purpose', 20)->default('activation');
            $table->uuid('account_id')->nullable();
            $table->string('password_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_activation_codes', fn (Blueprint $table) => $table->dropColumn(['purpose', 'account_id', 'password_fingerprint']));
    }
};
