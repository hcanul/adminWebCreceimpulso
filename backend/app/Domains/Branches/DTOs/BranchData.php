<?php

namespace App\Domains\Branches\DTOs;

final readonly class BranchData
{
    public function __construct(public array $values) {}

    public function fields(): array
    {
        return array_intersect_key($this->values, array_flip(['name', 'city', 'phone', 'email', 'address', 'is_active']));
    }

    public function fingerprint(string $actor): string
    {
        $fields = $this->fields();
        ksort($fields);

        return hash('sha256', json_encode([$actor, $fields], JSON_THROW_ON_ERROR));
    }
}
