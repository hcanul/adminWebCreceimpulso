<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('client_accounts', function(Blueprint $t) {
  $t->boolean('must_change_password')->default(false);
  $t->timestamp('temporary_password_expires_at')->nullable();
 }); }
 public function down(): void { Schema::table('client_accounts', fn(Blueprint $t) => $t->dropColumn(['must_change_password','temporary_password_expires_at'])); }
};
