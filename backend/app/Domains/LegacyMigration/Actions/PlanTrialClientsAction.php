<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Actions;

/** Conservative identity projection. Ambiguities are quarantined, never merged. */
final class PlanTrialClientsAction
{
    public function execute(array $records, array $sources, array $primaryCities): array
    {
        $curps = [];
        foreach ($records as $record) {
            $curp = strtoupper(trim((string) ($record['raw']['curp'] ?? '')));
            $curps[$curp] = ($curps[$curp] ?? 0) + 1;
        }
        $accepted = $quarantine = [];
        foreach ($records as $record) {
            $row = $record['raw'];
            $sid = $record['source_id'];
            $id = (string) $row['id'];
            $curp = strtoupper(trim((string) ($row['curp'] ?? '')));
            $city = (string) ($row['city_id'] ?? '');
            $branch = $sources[$sid][$city] ?? null;
            $first = trim(($row['pNombre'] ?? '').' '.($row['sNombre'] ?? ''));
            $last = trim(($row['aPaterno'] ?? '').' '.($row['aMaterno'] ?? ''));
            $reasons = [];
            if (! preg_match('/^[A-Z][AEIOUX][A-Z]{2}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/D', $curp)) {
                $reasons[] = 'INVALID_CURP_FORMAT';
            }
            if ($curps[$curp] > 1) {
                $reasons[] = 'DUPLICATE_CURP_REVIEW';
            }
            if (! $branch) {
                $reasons[] = 'UNMAPPED_BRANCH';
            }
            if ($city !== ($primaryCities[$sid] ?? null)) {
                $reasons[] = 'BRANCH_OWNERSHIP_REVIEW';
            }
            if ($first === '' || $last === '' || mb_strlen($first) > 255 || mb_strlen($last) > 255 || mb_strlen((string) ($row['dudtelefono'] ?? '')) > 255) {
                $reasons[] = 'INVALID_CLIENT_FIELDS';
            }
            foreach (['created_at', 'updated_at', 'deleted_at'] as $field) {
                $value = $row[$field] ?? null;
                $parsed = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value) : false;
                if ($value !== null && (! $parsed || $parsed->format('Y-m-d H:i:s') !== $value)) {
                    $reasons[] = 'INVALID_TIMESTAMP';
                }
            }
            if ($reasons !== []) {
                $quarantine[] = ['source_id' => $sid, 'legacy_id' => $id, 'reasons' => array_values(array_unique($reasons))];

                continue;
            }
            $accepted[] = ['source_id' => $sid, 'legacy_id' => $id, 'client' => [
                'id' => (new BuildConsolidationPlanAction)->candidateId($sid, 'demands', $id),
                'branch_id' => $branch, 'curp' => $curp, 'first_name' => $first, 'last_name' => $last,
                'phone' => $row['dudtelefono'] ?? null, 'address' => $row['dudcalle'] ?? null,
                // Trial only: no financial status inferred from legacy flags or incomplete history.
                'status' => empty($row['deleted_at']) ? 'prospecto' : 'baja', 'version' => 1,
                'created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null, 'deleted_at' => $row['deleted_at'] ?? null,
            ], 'profile' => $row];
        }

        return ['accepted' => $accepted, 'quarantine' => $quarantine];
    }
}
