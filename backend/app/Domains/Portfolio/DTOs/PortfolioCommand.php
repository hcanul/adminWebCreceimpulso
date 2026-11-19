<?php

namespace App\Domains\Portfolio\DTOs;

final readonly class PortfolioCommand
{
    public function __construct(public array $values) {}

    public function fingerprint(string $loanId): string
    {
        $values = $this->values;
        ksort($values);

        return hash('sha256', json_encode([$loanId, $values], JSON_THROW_ON_ERROR));
    }
}
