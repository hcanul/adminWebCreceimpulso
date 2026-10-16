<?php

namespace App\Domains\Credit\DTOs;

final readonly class ReversePaymentData
{
    public function __construct(public string $id, public string $version, public string $reason) {}

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->id, $this->version, $this->reason], JSON_THROW_ON_ERROR));
    }
}
