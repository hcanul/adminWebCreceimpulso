<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Actions;

use App\Domains\Identity\Services\FolioSequence;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Only usable against an explicitly marked rehearsal database. No existing data is overwritten. */
final class ApplyBranchClientsTrialAction
{
    public function execute(array $plan, string $marker): array
    {
        if (($plan['scope'] ?? null) !== 'branch_clients_rehearsal_only' || ($plan['ready_for_production'] ?? null) !== false) {
            throw new RuntimeException('Invalid rehearsal plan.');
        }

        return DB::transaction(function () use ($plan, $marker) {
            $lab = DB::table('branch_trial_marker')->lockForUpdate()->first();
            if (! $lab || ! hash_equals($lab->id, $marker)) {
                throw new RuntimeException('Rehearsal database marker does not match.');
            }
            $source = DB::table('branch_trial_sources')->where('source_id', $plan['source_id'])->first();
            if ($source && (! hash_equals($source->snapshot_sha256, $plan['snapshot_sha256']) || $source->branch_id !== $plan['branch']['id'])) {
                throw new RuntimeException('This source changed; create a new rehearsal instead of overwriting.');
            }
            if (! $source) {
                if (Branch::withTrashed()->whereKey($plan['branch']['id'])->exists()) {
                    throw new RuntimeException('Branch UUID already belongs to another source.');
                }
                $branch = new Branch;
                $branch->id = $plan['branch']['id'];
                $branch->fill(['name' => $plan['branch']['name'], 'city' => null, 'is_active' => true])->save();
                DB::table('branch_trial_sources')->insert(['source_id' => $plan['source_id'], 'branch_id' => $branch->id, 'snapshot_sha256' => $plan['snapshot_sha256']]);
            }
            $report = ['source_id' => $plan['source_id'], 'inserted' => 0, 'unchanged' => 0, 'quarantined' => count($plan['quarantine']), 'issues' => $plan['quarantine'], 'ready_for_production' => false];
            foreach ($plan['accepted'] as $entry) {
                $hash = hash('sha256', json_encode($entry, JSON_THROW_ON_ERROR));
                $mapping = DB::table('branch_trial_client_maps')->where('source_id', $plan['source_id'])->where('legacy_id', $entry['legacy_id'])->first();
                if ($mapping) {
                    $client = DB::table('clients')->where('id', $mapping->client_id)->first();
                    if (! $client || ! hash_equals($mapping->entry_sha256, $hash)) {
                        throw new RuntimeException('Imported source identity no longer matches.');
                    }
                    foreach ($entry['client'] as $field => $value) {
                        if (($client->$field === null) !== ($value === null) || (string) $client->$field !== (string) $value) {
                            throw new RuntimeException('Destination changed after import; no automatic overwrite.');
                        }
                    }
                    if ($client->folio !== $mapping->folio || json_decode($mapping->profile, true, 512, JSON_THROW_ON_ERROR) !== $entry['profile']) {
                        throw new RuntimeException('Destination folio/profile no longer matches.');
                    }
                    $report['unchanged']++;

                    continue;
                }
                if (DB::table('clients')->where('curp', $entry['client']['curp'])->exists()) {
                    $report['issues'][] = ['source_id' => $plan['source_id'], 'legacy_id' => $entry['legacy_id'], 'reasons' => ['CURP_EXISTS_IN_DESTINATION_REVIEW']];
                    $report['quarantined']++;

                    continue;
                }
                $client = $entry['client'];
                $client['folio'] = app(FolioSequence::class)->issue($client['branch_id'], 'client');
                DB::table('clients')->insert($client);
                DB::table('branch_trial_client_maps')->insert(['source_id' => $plan['source_id'], 'legacy_id' => $entry['legacy_id'],
                    'client_id' => $client['id'], 'folio' => $client['folio'], 'entry_sha256' => $hash,
                    'profile' => json_encode($entry['profile'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
                $saved = DB::table('clients')->where('id', $client['id'])->first();
                foreach ($client as $field => $value) {
                    if (($saved->$field === null) !== ($value === null) || (string) $saved->$field !== (string) $value) {
                        throw new RuntimeException('Client round-trip verification failed.');
                    }
                }
                $report['inserted']++;
            }

            return $report;
        }, 3);
    }
}
