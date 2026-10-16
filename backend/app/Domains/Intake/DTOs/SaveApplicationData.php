<?php

namespace App\Domains\Intake\DTOs;

final readonly class SaveApplicationData
{
    /** @param array<string, string|null> $fields */
    public function __construct(
        public string $id, public int $version, public string $teamId, public string $curp,
        public ?string $clientId, public ?int $clientVersion, public bool $confirmExisting,
        public bool $submit, public array $fields,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self($data['id'], (int) $data['version'], $data['team_id'], $data['curp'],
            $data['client_id'] ?? null, isset($data['client_version']) ? (int) $data['client_version'] : null,
            (bool) $data['confirm_existing'], (bool) $data['submit'], $data['fields']);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'version' => $this->version, 'team_id' => $this->teamId, 'curp' => $this->curp,
            'client_id' => $this->clientId, 'client_version' => $this->clientVersion, 'confirm_existing' => $this->confirmExisting,
            'submit' => $this->submit, 'fields' => $this->fields];
    }
}
