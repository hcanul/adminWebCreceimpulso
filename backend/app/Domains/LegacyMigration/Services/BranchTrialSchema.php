<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BranchTrialSchema
{
    public function create(string $marker): void
    {
        Schema::create('branch_trial_marker', fn (Blueprint $t) => $t->uuid('id')->primary());
        Schema::create('branch_trial_sources', function (Blueprint $t) {
            $t->string('source_id', 64)->primary();
            $t->foreignUuid('branch_id')->unique()->constrained()->restrictOnDelete();
            $t->string('snapshot_sha256', 64);
        });
        Schema::create('branch_trial_client_maps', function (Blueprint $t) {
            $t->string('source_id', 64);
            $t->string('legacy_id', 32);
            $t->foreignUuid('client_id')->unique()->constrained()->restrictOnDelete();
            $t->string('folio', 64);
            $t->string('entry_sha256', 64);
            $t->longText('profile');
            $t->primary(['source_id', 'legacy_id']);
        });
        DB::table('branch_trial_marker')->insert(['id' => $marker]);
    }
}
