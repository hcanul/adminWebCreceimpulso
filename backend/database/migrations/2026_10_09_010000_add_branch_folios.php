<?php

use App\Domains\Identity\Services\FolioSequence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope', 36);
            $table->string('kind', 16);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unique(['scope', 'kind']);
        });
        Schema::table('branches', fn (Blueprint $table) => $table->string('folio_code', 24)->nullable()->unique());
        foreach (['clients', 'loan_applications'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('folio', 64)->nullable()->unique());
        }
        $sequence = app(FolioSequence::class);
        // Stable one-time codes, including archived branches and records. Names are never identifiers.
        DB::table('branches')->orderBy('name')->orderBy('id')->get(['id'])->each(function ($branch) use ($sequence) {
            DB::table('branches')->where('id', $branch->id)->update(['folio_code' => $sequence->branchCode()]);
        });
        foreach (['clients' => 'client', 'loan_applications' => 'credit'] as $table => $kind) {
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($sequence, $table, $kind) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['folio' => $sequence->issue($row->branch_id, $kind)]);
                }
            });
            Schema::table($table, fn (Blueprint $table) => $table->string('folio', 64)->nullable(false)->change());
        }
        Schema::table('branches', fn (Blueprint $table) => $table->string('folio_code', 24)->nullable(false)->change());
    }

    public function down(): void
    {
        throw new RuntimeException('Los folios emitidos son permanentes; su reversión requiere un plan explícito de conservación.');
    }
};
