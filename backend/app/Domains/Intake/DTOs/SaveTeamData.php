<?php

namespace App\Domains\Intake\DTOs;

final readonly class SaveTeamData
{
    public function __construct(public string $name, public string $branchId, public string $promoterId) {}

    public function toArray(): array
    {
        return ['name' => $this->name, 'branch_id' => $this->branchId, 'promoter_id' => $this->promoterId];
    }
}
