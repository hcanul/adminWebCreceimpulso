<?php

namespace App\Domains\LateFees\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Pure calculation: callers must supply the confirmed base at each weekly cutoff. */
final class WeeklyLateFeeCalculator
{
    public const POLICY = 'weekly-started-10-percent-v1';

    public function chargeDates(string $dueOn, string $asOf): array
    {
        $due = $this->date($dueOn);
        $cutoff = $this->date($asOf);
        $dates = [];
        for ($date = $due->addDay(); $date->lessThanOrEqualTo($cutoff); $date = $date->addWeek()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /** 10% in integer cents, rounded half up per weekly charge, never compounded. */
    public function chargeCents(int $baseCents): int
    {
        if ($baseCents < 0 || $baseCents > 999999999999) {
            throw new InvalidArgumentException('La base de mora está fuera del intervalo permitido.');
        }

        return intdiv($baseCents + 5, 10);
    }

    /** Missing bases are rejected instead of silently reconstructing historical debt from today's balance. */
    public function assess(string $dueOn, string $asOf, array $basesByDate): array
    {
        $dates = $this->chargeDates($dueOn, $asOf);
        if (count($dates) !== count($basesByDate) || array_diff($dates, array_keys($basesByDate))) {
            throw new InvalidArgumentException('Se requiere la base de cada inicio de semana vencida.');
        }
        $total = 0;
        $charges = [];
        foreach ($dates as $index => $date) {
            $base = $basesByDate[$date];
            if (! is_int($base)) {
                throw new InvalidArgumentException('Los importes deben expresarse en centavos enteros.');
            }
            $amount = $this->chargeCents($base);
            $total += $amount;
            $charges[] = ['week' => $index + 1, 'charged_on' => $date, 'base_cents' => $base, 'amount_cents' => $amount];
        }

        return ['policy' => self::POLICY, 'total_cents' => $total, 'charges' => $charges];
    }

    private function date(string $value): CarbonImmutable
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new InvalidArgumentException('Usa una fecha válida con formato AAAA-MM-DD.');
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value, 'America/Cancun');
    }
}
