<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Actions;

use OverflowException;

/** Compares recorded headers/details only; does not invent a contractual balance. */
final class ReconcileLegacyPaymentsAction
{
    public function execute(array $tables): array
    {
        $credits = array_column($tables['credits'], null, 'id');
        $charges = array_column($tables['charges'], null, 'id');
        $members = array_column($tables['credit_groups'], null, 'id');
        $sums = $invalidCharges = $issues = $references = [];
        $totals = ['headers_not_deleted' => 0, 'details_not_deleted' => 0, 'details_without_header' => 0, 'headers_without_credit' => 0];
        $add = static function (int &$total, int $amount): void {
            if ($total > PHP_INT_MAX - $amount) {
                throw new OverflowException('Exact monetary sum overflow.');
            }
            $total += $amount;
        };
        foreach ($tables['billing_details'] as $row) {
            $id = (string) $row['id'];
            $chargeId = (string) $row['charge_id'];
            $header = $charges[$chargeId] ?? null;
            $member = $members[$row['credit_group_id']] ?? null;
            foreach (['header' => $header, 'participation' => $member] as $kind => $parent) {
                $state = $parent === null ? 'missing' : (empty($parent['deleted_at']) ? 'present_not_deleted' : 'present_deleted');
                $key = 'detail_'.(empty($row['deleted_at']) ? 'not_deleted' : 'deleted').'_to_'.$kind.'_'.$state;
                $references[$key] = ($references[$key] ?? 0) + 1;
            }
            if ($header && $member && ((string) $header['credit_id'] !== (string) $member['credit_id'] || (string) $row['demand_id'] !== (string) $member['demand_id'])) {
                $issues[] = ['code' => 'PAYMENT_RELATION_MISMATCH', 'table' => 'billing_details', 'legacy_id' => $id];
            }
            $amount = $this->cents($row['pago'] ?? null);
            if ($amount === null) {
                $issues[] = ['code' => 'INVALID_MONEY', 'table' => 'billing_details', 'legacy_id' => $id, 'field' => 'pago'];
                if (empty($row['deleted_at'])) {
                    $invalidCharges[$chargeId] = true;
                }

                continue;
            }
            if (empty($row['deleted_at'])) {
                $sums[$chargeId] ??= 0;
                $add($sums[$chargeId], $amount);
                $add($totals['details_not_deleted'], $amount);
                if (! $header) {
                    $add($totals['details_without_header'], $amount);
                }
            }
        }
        $matched = $mismatched = $blocked = $archived = 0;
        foreach ($charges as $id => $row) {
            $credit = $credits[$row['credit_id']] ?? null;
            $state = $credit === null ? 'missing' : (empty($credit['deleted_at']) ? 'present_not_deleted' : 'present_deleted');
            $key = 'header_'.(empty($row['deleted_at']) ? 'not_deleted' : 'deleted').'_to_credit_'.$state;
            $references[$key] = ($references[$key] ?? 0) + 1;
            $amount = $this->cents($row['pagoTotal'] ?? null);
            if ($amount === null) {
                $issues[] = ['code' => 'INVALID_MONEY', 'table' => 'charges', 'legacy_id' => (string) $id, 'field' => 'pagoTotal'];
            }
            if (! empty($row['deleted_at'])) {
                $archived++;

                continue;
            }
            if ($amount !== null) {
                $add($totals['headers_not_deleted'], $amount);
                if (! $credit) {
                    $add($totals['headers_without_credit'], $amount);
                }
            }
            if ($amount === null || isset($invalidCharges[$id])) {
                $blocked++;

                continue;
            }
            $sum = $sums[$id] ?? 0;
            if ($sum !== $amount) {
                $mismatched++;
                $issues[] = ['code' => 'PAYMENT_TOTAL_MISMATCH', 'table' => 'charges', 'legacy_id' => (string) $id,
                    'credit_legacy_id' => (string) $row['credit_id'], 'credit_reference' => $state,
                    'header_amount' => $this->decimal($amount), 'detail_sum' => $this->decimal($sum),
                    'header_minus_details' => $this->decimal($amount - $sum)];
            } else {
                $matched++;
            }
        }

        return ['ready_for_financial_import' => false, 'totals_mxn' => array_map($this->decimal(...), $totals),
            'headers_matched' => $matched, 'headers_mismatched' => $mismatched, 'headers_blocked_by_invalid_money' => $blocked,
            'headers_deleted_not_compared' => $archived, 'reference_classifications' => $references, 'issues' => $issues,
            'limitations' => ['not_deleted is a technical deletion flag, not an active-loan classification.', 'Invalid amounts are excluded from aggregates and reported; totals with exclusions are not accepted balances.', 'No interest, arrears, guarantees, reversals or outstanding contractual balance reconstructed.']];
    }

    private function cents(mixed $value): ?int
    {
        if (! is_string($value) || ! preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,2}))?$/D', $value, $match)) {
            return null;
        }

        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    private function decimal(int $value): string
    {
        return ($value < 0 ? '-' : '').intdiv(abs($value), 100).'.'.str_pad((string) (abs($value) % 100), 2, '0', STR_PAD_LEFT);
    }
}
