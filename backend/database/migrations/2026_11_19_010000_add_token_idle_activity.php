<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->timestamp('last_activity_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropColumn('last_activity_at'));
    }
};
