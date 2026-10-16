<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoters', function (Blueprint $table) {
            // Identidad compartida con el usuario: solo un perfil Promotor puede registrarse.
            $table->uuid('id')->primary();
            $table->foreign('id')->references('id')->on('users')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $morph = (new User)->getMorphClass();
        $users = DB::table('users')->where('is_active', true)->whereNull('deleted_at')->whereIn('branch_id', DB::table('branches')->whereNull('deleted_at')->select('id'))->whereIn('id', DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', 'Promotor')->where('roles.guard_name', 'web')->where('model_type', $morph)->select('model_id'))->get();
        foreach ($users as $user) {
            DB::table('promoters')->insert(['id' => $user->id, 'branch_id' => $user->branch_id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['promoter_id']);
            $table->uuid('promoter_id')->nullable()->change();
            $table->uuid('previous_promoter_user_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        foreach (DB::table('teams')->get(['id', 'branch_id', 'promoter_id']) as $team) {
            if (! DB::table('promoters')->where('id', $team->promoter_id)->where('branch_id', $team->branch_id)->exists()) {
                DB::table('teams')->where('id', $team->id)->update(['previous_promoter_user_id' => $team->promoter_id, 'promoter_id' => null]);
            }
        }
        Schema::table('teams', fn (Blueprint $table) => $table->foreign('promoter_id')->references('id')->on('promoters')->restrictOnDelete());
    }

    public function down(): void
    {
        if (DB::table('teams')->whereNull('promoter_id')->whereNull('previous_promoter_user_id')->exists()) {
            throw new RuntimeException('Asigna los grupos pendientes antes de revertir.');
        }
        Schema::table('teams', fn (Blueprint $table) => $table->dropForeign(['promoter_id']));
        DB::table('teams')->whereNull('promoter_id')->update(['promoter_id' => DB::raw('previous_promoter_user_id')]);
        Schema::table('teams', function (Blueprint $table) {
            $table->uuid('promoter_id')->nullable(false)->change();
            $table->foreign('promoter_id')->references('id')->on('users')->restrictOnDelete();
            $table->dropColumn('previous_promoter_user_id');
            $table->dropConstrainedForeignId('created_by');
        });
        Schema::dropIfExists('promoters');
    }
};
