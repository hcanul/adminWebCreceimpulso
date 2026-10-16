<?php

namespace App\Domains\Credit\Services;

use Illuminate\Validation\ValidationException;

final class CreditCalculator
{
    public const WEEKS = 16;

    public static function scaled(string $value, int $places = 2): int
    {
        if (! preg_match('/^\d{1,12}(?:\.\d{1,'.$places.'})?$/D', $value)) {
            throw ValidationException::withMessages(['amount' => 'Usa un importe positivo con hasta '.$places.' decimales, sin comas.']);
        }
        [$whole,$fraction] = array_pad(explode('.', $value), 2, '');

        return (int) $whole * (10 ** $places) + (int) str_pad($fraction, $places, '0');
    }

    public static function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function estimate(int $principal, string $factor): array
    {
        $rate = self::scaled($factor, 4);
        // Principal cents × pesos per 1,000 per week. Half-up rounding in integer cents.
        $weekly = intdiv($principal * $rate + 5000000, 10000000);
        $total = $weekly * self::WEEKS;
        if ($principal <= 0 || $total < $principal) {
            throw ValidationException::withMessages(['rate_id' => 'La tasa produce un total inferior al capital. Revisa su configuración.']);
        }
        $guarantee = intdiv($principal + 5, 10);

        return ['principal' => self::money($principal), 'weekly_payment' => self::money($weekly), 'total_repayment' => self::money($total), 'interest' => self::money($total - $principal), 'guarantee' => self::money($guarantee), 'net_delivery' => self::money($principal - $guarantee)];
    }
}
