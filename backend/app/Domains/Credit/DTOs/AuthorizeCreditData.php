<?php

namespace App\Domains\Credit\DTOs;

final readonly class AuthorizeCreditData
{
    public function __construct(public int $version, public string $decision, public string $notes, public ?string $firstDueOn = null) {}

    public static function fromArray(array $data): self
    {
        return new self($data['version'], $data['decision'], trim($data['notes']), $data['first_due_on'] ?? null);
    }
}
