<?php

namespace App\Domains\Credit\DTOs;

final readonly class ReviewCreditData
{
    public function __construct(public int $version, public string $action, public array $checks, public string $notes) {}

    public static function fromArray(array $data): self
    {
        return new self($data['version'], $data['action'], $data['checks'] ?? [], trim($data['notes'] ?? ''));
    }
}
