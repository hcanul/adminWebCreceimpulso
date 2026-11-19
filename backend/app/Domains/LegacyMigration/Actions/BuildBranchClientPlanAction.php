<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Actions;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/** A single-branch, immutable rehearsal plan; no financial/workflow state is inferred. */
final class BuildBranchClientPlanAction
{
    public function execute(array $snapshot): array
    {
        $sid = $snapshot['source_id'] ?? '';
        $branch = $snapshot['branch'] ?? [];
        $rows = $snapshot['demands'] ?? null;
        if (! is_string($sid) || ! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $sid)
            || ! is_string($branch['id'] ?? null) || ! Uuid::isValid($branch['id'])
            || ! is_string($branch['name'] ?? null) || trim($branch['name']) === '' || mb_strlen($branch['name']) > 255
            || ! preg_match('/^[1-9][0-9]*$/D', (string) ($branch['legacy_city_id'] ?? ''))
            || ! is_array($rows) || ! array_is_list($rows)) {
            throw new InvalidArgumentException('Invalid branch snapshot.');
        }
        if (($snapshot['source_timezone_confirmed'] ?? false) !== true) {
            throw new InvalidArgumentException('Confirm the legacy timestamp timezone before planning.');
        }
        $zone = new DateTimeZone($snapshot['source_timezone'] ?? 'INVALID');
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Invalid source row.');
            }
            $id = (string) ($row['id'] ?? '');
            if (! preg_match('/^[1-9][0-9]*$/D', $id) || isset($seen[$id])) {
                throw new InvalidArgumentException('Invalid or repeated source client ID.');
            }
            $seen[$id] = true;
        }
        $records = array_map(fn ($row) => ['source_id' => $sid, 'raw' => $row], $rows);
        $city = (string) $branch['legacy_city_id'];
        $plan = (new PlanTrialClientsAction)->execute($records, [$sid => [$city => $branch['id']]], [$sid => $city]);
        foreach ($plan['accepted'] as &$entry) {
            foreach (['created_at', 'updated_at', 'deleted_at'] as $field) {
                if ($entry['client'][$field] !== null) {
                    $entry['client'][$field] = (new DateTimeImmutable($entry['client'][$field], $zone))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                }
            }
        }
        unset($entry);

        return ['version' => 1, 'scope' => 'branch_clients_rehearsal_only', 'ready_for_production' => false,
            'source_id' => $sid, 'branch' => $branch, 'source_timezone' => $zone->getName(),
            'source_rows' => count($rows), 'snapshot_sha256' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'accepted' => $plan['accepted'], 'quarantine' => $plan['quarantine']];
    }
}
