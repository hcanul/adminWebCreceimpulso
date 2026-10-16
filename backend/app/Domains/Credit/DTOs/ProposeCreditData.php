<?php

namespace App\Domains\Credit\DTOs;

final readonly class ProposeCreditData
{
    public function __construct(public int $version, public array $participants, public string $disbursementMethod, public string $repaymentMethod, public string $notes) {}

    public static function fromArray(array $data): self
    {
        return new self($data['version'], $data['participants'], $data['disbursement_method'], $data['repayment_method'], trim($data['notes']));
    }
}
