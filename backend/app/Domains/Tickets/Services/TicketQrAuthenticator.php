<?php

declare(strict_types=1);

namespace App\Domains\Tickets\Services;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/** Authenticates an opaque ticket reference; does NOT authorize payment or prevent replay by itself. */
final readonly class TicketQrAuthenticator
{
    public function __construct(private string $key, private string $keyId)
    {
        if (strlen($key) < 32 || ! preg_match('/^[a-zA-Z0-9_-]{1,32}$/D', $keyId)) {
            throw new InvalidArgumentException('Use a dedicated random key of at least 32 bytes and a valid key ID.');
        }
    }

    public function issue(string $ticketId, string $purpose, int $issuedAt, int $expiresAt): string
    {
        if (! Uuid::isValid($ticketId) || ! in_array($purpose, ['receipt'], true) || $issuedAt < 0 || $expiresAt <= $issuedAt) {
            throw new InvalidArgumentException('Invalid ticket claims.');
        }
        $body = $this->encode(json_encode(['v' => 1, 'kid' => $this->keyId, 'ticket_id' => $ticketId, 'purpose' => $purpose, 'iat' => $issuedAt, 'exp' => $expiresAt], JSON_THROW_ON_ERROR));

        return $body.'.'.$this->encode(hash_hmac('sha256', $body, $this->key, true));
    }

    public function verify(string $token, string $expectedPurpose, int $now): ?array
    {
        if (strlen($token) > 2048 || $now < 0 || ! in_array($expectedPurpose, ['receipt'], true)) {
            return null;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 2 || ! preg_match('/^[A-Za-z0-9_-]+$/D', $parts[0])) {
            return null;
        }
        $signature = $this->encode(hash_hmac('sha256', $parts[0], $this->key, true));
        if (! hash_equals($signature, $parts[1])) {
            return null;
        }
        $decoded = base64_decode(strtr($parts[0], '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }
        try {
            $c = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (! is_array($c) || ($c['v'] ?? null) !== 1 || ($c['kid'] ?? null) !== $this->keyId || ($c['purpose'] ?? null) !== $expectedPurpose
            || ! is_string($c['ticket_id'] ?? null) || ! Uuid::isValid($c['ticket_id']) || ! is_int($c['iat'] ?? null) || ! is_int($c['exp'] ?? null)
            || $c['iat'] < 0 || $c['iat'] > $now || $c['exp'] <= $now || $c['exp'] <= $c['iat']) {
            return null;
        }

        return $c;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
