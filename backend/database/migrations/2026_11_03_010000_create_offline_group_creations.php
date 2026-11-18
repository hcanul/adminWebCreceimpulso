<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_group_creations', function (Blueprint $t) {
            $t->foreignUuid('id')->primary()->constrained('teams')->restrictOnDelete();
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('request_hash', 64);
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Conservar correspondencias de grupos offline.');
    }
};
