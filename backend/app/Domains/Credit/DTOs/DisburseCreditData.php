<?php

namespace App\Domains\Credit\DTOs;

final readonly class DisburseCreditData
{
    public function __construct(public array $values) {}

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->values, JSON_THROW_ON_ERROR));
    }

    public static function fromArray(array $data): self
    {
        return new self([
            'id' => $data['id'], 'version' => (int) $data['authorization_version'],
            'disbursed_on' => $data['disbursed_on'], 'first_due_on' => $data['first_due_on'],
            'bank_name' => trim($data['bank_name'] ?? '') ?: null,
            'bank_reference' => trim($data['bank_reference'] ?? '') ?: null,
            'notes' => trim($data['notes']),
        ]);
    }
}
