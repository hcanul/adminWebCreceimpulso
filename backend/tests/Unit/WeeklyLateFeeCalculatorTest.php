<?php

namespace Tests\Unit;

use App\Domains\LateFees\Services\WeeklyLateFeeCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeeklyLateFeeCalculatorTest extends TestCase
{
    #[DataProvider('cutoffs')]
    public function test_charge_is_created_on_first_day_of_each_started_week(string $asOf, array $dates): void
    {
        $this->assertSame($dates, (new WeeklyLateFeeCalculator)->chargeDates('2026-10-18', $asOf));
    }

    public static function cutoffs(): array
    {
        return [
            ['2026-10-17', []], ['2026-10-18', []],
            ['2026-10-19', ['2026-10-19']], ['2026-10-25', ['2026-10-19']],
            ['2026-10-26', ['2026-10-19', '2026-10-26']],
            ['2026-11-01', ['2026-10-19', '2026-10-26']],
            ['2026-11-02', ['2026-10-19', '2026-10-26', '2026-11-02']],
        ];
    }

    public function test_confirmed_example_accumulates_160_without_compounding(): void
    {
        $result = (new WeeklyLateFeeCalculator)->assess('2026-10-18', '2026-10-26', ['2026-10-19' => 80000, '2026-10-26' => 80000]);
        $this->assertSame(16000, $result['total_cents']);
        $this->assertSame([8000, 8000], array_column($result['charges'], 'amount_cents'));
    }

    public function test_supplied_historical_bases_preserve_previously_accrued_charges(): void
    {
        // This tests explicit inputs, not a decision about how partial payments determine them.
        $result = (new WeeklyLateFeeCalculator)->assess('2026-10-18', '2026-11-02', ['2026-10-19' => 80000, '2026-10-26' => 30000, '2026-11-02' => 0]);
        $this->assertSame(11000, $result['total_cents']);
        $this->assertSame([8000, 3000, 0], array_column($result['charges'], 'amount_cents'));
    }

    public function test_missing_history_is_not_inferred_from_current_balance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WeeklyLateFeeCalculator)->assess('2026-10-18', '2026-10-26', ['2026-10-26' => 30000]);
    }

    public function test_rounding_and_zero_balance(): void
    {
        $calculator = new WeeklyLateFeeCalculator;
        $this->assertSame(0, $calculator->chargeCents(0));
        $this->assertSame(8000, $calculator->chargeCents(80004));
        $this->assertSame(8001, $calculator->chargeCents(80005));
    }

    public function test_leap_year_and_year_boundary(): void
    {
        $calculator = new WeeklyLateFeeCalculator;
        $this->assertSame(['2028-02-29', '2028-03-07'], $calculator->chargeDates('2028-02-28', '2028-03-07'));
        $this->assertSame(['2027-01-01'], $calculator->chargeDates('2026-12-31', '2027-01-01'));
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_dates_are_rejected(string $date): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WeeklyLateFeeCalculator)->chargeDates($date, '2026-10-18');
    }

    public static function invalidInputs(): array
    {
        return [['2026-02-30'], ['2026-2-01'], ['2026-10-18T00:00:00Z'], ['0000-01-01'], ['yesterday']];
    }

    public function test_negative_base_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WeeklyLateFeeCalculator)->chargeCents(-1);
    }
}
