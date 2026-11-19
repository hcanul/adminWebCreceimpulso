<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Actions;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/** Pure planning: preserves historical text, never opens Laravel or a database. */
final class PlanCajasFollowupAction
{
    public function execute(array $snapshot, array $mapping): array
    {
        $tables = $snapshot['tables'] ?? [];
        foreach (['database_connections', 'payment_observation_entries', 'overdue_payment_notes', 'users'] as $name) {
            if (! isset($tables[$name]) || ! is_array($tables[$name]) || ! array_is_list($tables[$name])) {
                throw new InvalidArgumentException('Missing central source table.');
            }
        }
        $branches = [];
        foreach ($mapping['branches'] ?? [] as $row) {
            $key = $this->id($row['central_branch_id'] ?? null);
            $this->uuid($row['branch_id'] ?? null);
            $source = $row['source_id'] ?? '';
            if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $source) || isset($branches[$key])) {
                throw new InvalidArgumentException('Invalid explicit source map.');
            }
            $branches[$key] = $row;
        }
        $members = [];
        foreach ($mapping['participations'] ?? [] as $row) {
            foreach (['branch_id', 'loan_application_id', 'loan_participation_id', 'client_id'] as $field) {
                $this->uuid($row[$field] ?? null);
            }
            $key = ($row['source_id'] ?? '').':'.$this->id($row['legacy_credit_group_id'] ?? null);
            $this->id($row['legacy_credit_id'] ?? null);
            $this->id($row['legacy_demand_id'] ?? null);
            if (isset($members[$key])) {
                throw new InvalidArgumentException('Ambiguous member map.');
            }
            $members[$key] = $row;
        }
        $users = [];
        foreach ($tables['users'] as $row) {
            $users[$this->id($row['id'] ?? null)] = $row['name'] ?? null;
        }
        $sourceBranches = [];
        foreach ($tables['database_connections'] as $row) {
            $sourceBranches[$this->id($row['id'] ?? null)] = true;
        }
        $rows = [];
        $quarantine = [];
        $seen = [];
        foreach (['payment_observation_entries', 'overdue_payment_notes'] as $table) {
            foreach ($tables[$table] as $row) {
                $origin = $table.':'.$this->id($row['id'] ?? null);
                if (isset($seen[$origin])) {
                    throw new InvalidArgumentException('Duplicate source identity.');
                }
                $seen[$origin] = true;
                $text = $row['observation'] ?? null;
                $branchKey = (string) ($row['database_connection_id'] ?? '');
                $branch = $branches[$branchKey] ?? null;
                $member = $branch ? ($members[$branch['source_id'].':'.($row['credit_group_id'] ?? '')] ?? null) : null;
                $reason = ! isset($sourceBranches[$branchKey]) || ! $branch ? 'unmapped_branch' : (! $member ? 'unmapped_participation' : null);
                if ($member && $member['branch_id'] !== $branch['branch_id']) {
                    $reason = 'branch_mismatch';
                }
                foreach (['credit_id' => 'legacy_credit_id', 'demand_id' => 'legacy_demand_id'] as $field => $legacy) {
                    if ($member && isset($row[$field]) && (string) $row[$field] !== (string) $member[$legacy]) {
                        $reason = 'relationship_mismatch';
                    }
                }
                $created = $table === 'overdue_payment_notes' ? ($row['updated_at'] ?? $row['created_at'] ?? null) : ($row['created_at'] ?? null);
                if (! is_string($text) || trim($text) === '' || mb_strlen($text) > 2000) {
                    $reason = 'invalid_observation';
                }
                if (! $this->validDate($created)) {
                    $reason = 'invalid_timestamp';
                }
                if ($reason) {
                    $quarantine[] = ['origin_id' => $origin, 'reason' => $reason, 'raw' => $row];

                    continue;
                }
                // A current-note cache is not a second event when the same source event exists.
                if ($table === 'overdue_payment_notes') {
                    $represented = false;
                    foreach ($tables['payment_observation_entries'] as $entry) {
                        if ((string) ($entry['database_connection_id'] ?? '') === $branchKey
                            && (string) ($entry['credit_group_id'] ?? '') === (string) $row['credit_group_id']
                            && ($entry['observation'] ?? null) === $text && ($entry['created_at'] ?? null) === $created
                            && (string) ($entry['user_id'] ?? '') === (string) ($row['updated_by'] ?? '')) {
                            $represented = true;
                            break;
                        }
                    }
                    if ($represented) {
                        continue;
                    }
                }
                $author = $row['author_name'] ?? $users[(string) ($row['user_id'] ?? $row['updated_by'] ?? '')] ?? null;
                $rows[] = ['id' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'creceimpulso:cajas-followup:'.$origin)->toString(),
                    'branch_id' => $member['branch_id'], 'loan_application_id' => $member['loan_application_id'],
                    'loan_participation_id' => $member['loan_participation_id'], 'client_id' => $member['client_id'],
                    // Historical authors are never linked to a current account by matching numeric IDs.
                    'actor_id' => null, 'actor_name' => is_string($author) && trim($author) !== '' ? $author : 'Autor histórico no identificado',
                    'observation' => $text, 'created_at_source' => $created, 'source_timezone' => $mapping['source_timezone'] ?? null,
                    'origin_source' => 'cajas_central', 'origin_id' => $origin, 'raw' => $row];
            }
        }

        return ['version' => 1, 'ready_for_import' => false, 'requires_verified_target_mapping' => true,
            'requires_source_timezone_confirmation' => true, 'planned_count' => count($rows), 'quarantine_count' => count($quarantine),
            'entries' => $rows, 'quarantine' => $quarantine];
    }

    private function id(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^[1-9][0-9]*$/D', (string) $value)) {
            throw new InvalidArgumentException('Invalid legacy ID.');
        }

        return (string) $value;
    }

    private function uuid(mixed $value): void
    {
        if (! is_string($value) || ! Uuid::isValid($value)) {
            throw new InvalidArgumentException('Explicit target UUID required.');
        }
    }

    private function validDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);

        return $date !== false && $date->format('Y-m-d H:i:s') === $value;
    }
}
