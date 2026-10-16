<?php

namespace App\Domains\Credit\DTOs;

use App\Domains\Credit\Services\CreditCalculator;

final readonly class CreateCreditData
{
    public function __construct(public string $id, public string $teamId, public string $rateId, public string $rateSignature, public string $memberLimit, public array $participants) {}

    public static function fromArray(array $data): self
    {
        $participants = array_map(fn ($row) => ['client_id' => $row['client_id'], 'amount_requested' => CreditCalculator::money(CreditCalculator::scaled($row['amount_requested']))], $data['participants']);
        usort($participants, fn ($a, $b) => strcmp($a['client_id'], $b['client_id']));

        return new self($data['id'], $data['team_id'], $data['rate_id'], $data['rate_signature'], CreditCalculator::money(CreditCalculator::scaled($data['member_limit'])), $participants);
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->teamId, $this->rateId, $this->rateSignature, $this->memberLimit, $this->participants], JSON_THROW_ON_ERROR));
    }
}
