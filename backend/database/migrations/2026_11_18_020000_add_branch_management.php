<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $t) {
            $t->string('phone', 40)->nullable();
            $t->string('email', 254)->nullable();
            $t->text('address')->nullable();
            $t->unsignedInteger('management_version')->default(0);
            $t->string('creation_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('branches', fn (Blueprint $t) => $t->dropColumn(['phone', 'email', 'address', 'management_version', 'creation_hash']));
    }
};
