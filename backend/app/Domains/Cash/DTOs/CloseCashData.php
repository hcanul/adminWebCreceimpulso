<?php

namespace App\Domains\Cash\DTOs;

use App\Domains\Credit\Services\CreditCalculator;

final readonly class CloseCashData
{
    public function __construct(public array $values) {}

    public static function fromArray(array $v): self
    {
        return new self(['id' => $v['id'], 'branch_id' => $v['branch_id'], 'business_date' => $v['business_date'], 'version' => $v['version'], 'cash_counted' => CreditCalculator::money(CreditCalculator::scaled($v['cash_counted'])), 'notes' => trim($v['notes'] ?? '') ?: null]);
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->values, JSON_THROW_ON_ERROR));
    }
}
