<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_client_creations', function (Blueprint $t) {
            $t->foreignUuid('id')->primary()->constrained('client_applications')->restrictOnDelete();
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->uuid('proposed_client_id');
            $t->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $t->string('request_hash', 64);
            $t->unsignedInteger('application_version');
            $t->unsignedInteger('client_version');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Conservar correspondencias de clientes offline.');
    }
};
