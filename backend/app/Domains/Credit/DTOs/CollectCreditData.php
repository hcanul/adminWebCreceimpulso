<?php

namespace App\Domains\Credit\DTOs;

use App\Domains\Credit\Services\CreditCalculator;

final readonly class CollectCreditData
{
    public function __construct(public array $values) {}

    public static function fromArray(array $data): self
    {
        return new self([
            'id' => $data['id'], 'balance_version' => $data['balance_version'],
            'amount' => CreditCalculator::money(CreditCalculator::scaled($data['amount'])),
            'method' => $data['method'], 'bank_name' => trim($data['bank_name'] ?? '') ?: null,
            'bank_reference' => trim($data['bank_reference'] ?? '') ?: null,
            'deposited_on' => $data['deposited_on'] ?? null, 'notes' => trim($data['notes'] ?? '') ?: null,
            'contributions' => array_map(fn ($r) => ['client_id' => $r['client_id'], 'payer_client_id' => $r['payer_client_id'], 'amount' => CreditCalculator::money(CreditCalculator::scaled($r['amount']))], $data['contributions']),
        ]);
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->values, JSON_THROW_ON_ERROR));
    }
}
