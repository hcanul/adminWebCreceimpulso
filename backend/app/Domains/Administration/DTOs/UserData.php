<?php

namespace App\Domains\Administration\DTOs;

final readonly class UserData
{
    public function __construct(public string $name, public string $email, public ?string $password, public bool $active, public ?string $branchId, public array $roles) {}

    public static function fromArray(array $data): self
    {
        return new self($data['name'], $data['email'], $data['password'] ?? null, (bool) $data['is_active'], $data['branch_id'] ?? null, $data['roles']);
    }
}
