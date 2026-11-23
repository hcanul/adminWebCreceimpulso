<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL can retain DDL from an interrupted migration. Preserve existing
        // preferences when retrying instead of adding the column or resetting it.
        if (! Schema::hasColumn('users', 'admin_onboarding_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('admin_onboarding_enabled')->default(true);
            });
            // Existing staff can use Help without being treated as newly enrolled users.
            DB::table('users')->update(['admin_onboarding_enabled' => false]);
        }

        if (Schema::hasTable('user_onboarding_visits')) {
            return;
        }

        Schema::create('user_onboarding_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tour', 40);
            $table->unsignedTinyInteger('login_number');
            $table->foreignId('token_id')->nullable()->unique()->constrained('personal_access_tokens')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['user_id', 'tour', 'login_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_onboarding_visits');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_onboarding_enabled');
        });
    }
};
