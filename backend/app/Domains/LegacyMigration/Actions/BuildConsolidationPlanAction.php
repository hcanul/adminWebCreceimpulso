<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Actions;

use App\Domains\LegacyMigration\DTOs\SourceSnapshot;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/** Read-only structural preflight. Never connects to a database or imports rows. */
final class BuildConsolidationPlanAction
{
    public const TABLES = ['citys', 'promoters', 'teams', 'demands', 'rates', 'credits', 'credit_groups', 'paydays', 'charges', 'billing_details', 'latepayments'];

    private const NAMESPACE = '099c5f8a-52c2-4f68-9ad8-9365a01a62c1';

    private const REFERENCES = [
        'promoters' => ['city_id' => 'citys'],
        'teams' => ['city_id' => 'citys', 'promoter_id' => 'promoters'],
        'demands' => ['city_id' => 'citys', 'team_id' => 'teams', 'promoter_id' => 'promoters'],
        'credits' => ['city_id' => 'citys', 'team_id' => 'teams', 'promoter_id' => 'promoters', 'tasaAut' => 'rates'],
        'credit_groups' => ['credit_id' => 'credits', 'demand_id' => 'demands'],
        'paydays' => ['credit_id' => 'credits'],
        'charges' => ['credit_id' => 'credits'],
        'billing_details' => ['charge_id' => 'charges', 'demand_id' => 'demands', 'credit_group_id' => 'credit_groups'],
        'latepayments' => ['group_id' => 'teams', 'credit_id' => 'credits'],
    ];

    public function candidateId(string $source, string $table, string $id): string
    {
        // JSON framing avoids ambiguous delimiter concatenations. Never change this namespace after import.
        return Uuid::uuid5(self::NAMESPACE, json_encode([$source, $table, $id], JSON_THROW_ON_ERROR))->toString();
    }

    /** @param list<SourceSnapshot> $sources */
    public function execute(array $sources): array
    {
        if ($sources === []) {
            throw new InvalidArgumentException('At least one source is required.');
        }
        $seenSources = $curps = $issues = $counts = $identities = [];
        foreach ($sources as $source) {
            if (! $source instanceof SourceSnapshot || isset($seenSources[$source->sourceId])) {
                throw new InvalidArgumentException('Sources must be typed and unique.');
            }
            $seenSources[$source->sourceId] = true;
            $sid = $source->sourceId;
            $index = [];
            $issue = static function (string $code, string $table, string $id = '', ?string $field = null) use (&$issues, $sid): void {
                $issues[] = array_filter(['code' => $code, 'source_id' => $sid, 'table' => $table, 'legacy_id' => $id, 'field' => $field], static fn ($v) => $v !== null);
            };
            foreach (self::TABLES as $table) {
                if (! array_key_exists($table, $source->tables)) {
                    $issue('MISSING_TABLE', $table);
                }
            }
            foreach ($source->tables as $table => $rows) {
                $counts[$sid][$table] = count($rows);
                if (! in_array($table, self::TABLES, true)) {
                    $issue('TABLE_OUTSIDE_PREFLIGHT_SCOPE', $table);
                }
                foreach ($rows as $row) {
                    $id = (string) $row['id'];
                    if (isset($index[$table][$id])) {
                        $issue('DUPLICATE_ID', $table, $id);

                        continue;
                    }
                    $index[$table][$id] = $row;
                    $identities[] = ['source_id' => $sid, 'table' => $table, 'legacy_id' => $id, 'candidate_uuid' => $this->candidateId($sid, $table, $id)];
                }
            }
            foreach ($index['citys'] ?? [] as $id => $row) {
                if (! isset($source->branches[$id])) {
                    $issue('UNMAPPED_BRANCH', 'citys', (string) $id);
                }
            }
            foreach ($source->branches as $id => $uuid) {
                if (! isset($index['citys'][$id])) {
                    $issue('UNKNOWN_BRANCH_MAPPING', 'citys', (string) $id);
                }
            }
            foreach (self::REFERENCES as $table => $references) {
                foreach ($index[$table] ?? [] as $id => $row) {
                    foreach ($references as $field => $target) {
                        $fk = $row[$field] ?? null;
                        if ((! is_int($fk) && ! is_string($fk)) || ! isset($index[$target][(string) $fk])) {
                            $issue('MISSING_REFERENCE', $table, (string) $id, $field);
                        }
                    }
                }
            }
            foreach ($index['demands'] ?? [] as $id => $row) {
                $curp = strtoupper(trim((string) ($row['curp'] ?? '')));
                if (! preg_match('/^[A-Z][AEIOUX][A-Z]{2}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/D', $curp)) {
                    $issue('INVALID_CURP_FORMAT', 'demands', (string) $id);

                    continue;
                }
                // Fingerprint stays in memory; the report never prints names or CURPs.
                $curps[hash('sha256', $curp)][] = ['source_id' => $sid, 'legacy_id' => (string) $id];
            }
            $participations = [];
            foreach ($index['credit_groups'] ?? [] as $id => $row) {
                $pair = json_encode([(string) ($row['credit_id'] ?? ''), (string) ($row['demand_id'] ?? '')]);
                if (isset($participations[$pair])) {
                    $issue('DUPLICATE_PARTICIPATION', 'credit_groups', (string) $id);
                }
                $participations[$pair] = true;
            }
            foreach ($index['credits'] ?? [] as $id => $row) {
                $team = $index['teams'][$row['team_id'] ?? ''] ?? null;
                if ($team && (string) ($team['city_id'] ?? '') !== (string) ($row['city_id'] ?? '')) {
                    $issue('BRANCH_MISMATCH', 'credits', (string) $id);
                }
            }
            $sums = [];
            foreach ($index['billing_details'] ?? [] as $id => $row) {
                $charge = $index['charges'][$row['charge_id'] ?? ''] ?? null;
                $member = $index['credit_groups'][$row['credit_group_id'] ?? ''] ?? null;
                if ($charge && $member && ((string) $charge['credit_id'] !== (string) $member['credit_id'] || (string) ($row['demand_id'] ?? '') !== (string) $member['demand_id'])) {
                    $issue('PAYMENT_RELATION_MISMATCH', 'billing_details', (string) $id);
                }
                $amount = $this->cents($row['pago'] ?? null);
                if ($amount === null) {
                    $issue('INVALID_MONEY_REQUIRES_REVIEW', 'billing_details', (string) $id, 'pago');

                    continue;
                }
                if (empty($row['deleted_at'])) {
                    $key = (string) ($row['charge_id'] ?? '');
                    $previous = $sums[$key] ?? 0;
                    if ($previous > PHP_INT_MAX - $amount) {
                        $issue('MONEY_OVERFLOW', 'billing_details', (string) $id);

                        continue;
                    }
                    $sums[$key] = $previous + $amount;
                }
            }
            foreach ($index['charges'] ?? [] as $id => $row) {
                $amount = $this->cents($row['pagoTotal'] ?? null);
                if ($amount === null) {
                    $issue('INVALID_MONEY_REQUIRES_REVIEW', 'charges', (string) $id, 'pagoTotal');
                } elseif (empty($row['deleted_at']) && $amount !== ($sums[(string) $id] ?? 0)) {
                    $issue('PAYMENT_TOTAL_MISMATCH', 'charges', (string) $id);
                }
            }
        }
        $duplicates = array_values(array_filter($curps, static fn ($refs) => count($refs) > 1));

        return ['version' => 1, 'mode' => 'dry-run', 'ready_for_import' => false,
            'structural_checks_passed' => $issues === [] && $duplicates === [],
            'counts' => $counts, 'identity_candidates' => $identities, 'issues' => $issues,
            'identity_reviews' => $duplicates,
            'limitations' => ['No data imported or identities merged.', 'No live schema validation or complete balance reconciliation.', 'Historical requested amounts, rate meaning, due dates, committee and branch ownership require review.', 'UUID candidates identify source records; they are not final merged client identities.']];
    }

    private function cents(mixed $value): ?int
    {
        // Require decimal strings: JSON floating-point values are not a trustworthy money interchange.
        if (! is_string($value) || ! preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            return null;
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }
}
