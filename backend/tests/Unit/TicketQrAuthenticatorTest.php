<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Tickets\Services\TicketQrAuthenticator;
use PHPUnit\Framework\TestCase;

final class TicketQrAuthenticatorTest extends TestCase
{
    public function test_valid_reference_and_expiry_boundary(): void
    {
        $a = new TicketQrAuthenticator(str_repeat('x', 32), 'test');
        $t = $a->issue('706a4a62-f9b9-4b30-94db-5a3522ca18ed', 'receipt', 100, 200);
        self::assertNotNull($a->verify($t, 'receipt', 199));
        self::assertNull($a->verify($t, 'receipt', 200));
        self::assertNull($a->verify($t, 'receipt', 99));
    }

    public function test_tampering_wrong_key_and_wrong_purpose_are_rejected(): void
    {
        $a = new TicketQrAuthenticator(str_repeat('x', 32), 'test');
        $t = $a->issue('706a4a62-f9b9-4b30-94db-5a3522ca18ed', 'receipt', 100, 200);
        self::assertNull($a->verify('a'.substr($t, 1), 'receipt', 150));
        self::assertNull($a->verify($t, 'payment-intent', 150));
        self::assertNull((new TicketQrAuthenticator(str_repeat('y', 32), 'test'))->verify($t, 'receipt', 150));
    }

    public function test_short_key_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TicketQrAuthenticator('weak', 'test');
    }

    public function test_malformed_token_is_rejected(): void
    {
        $a = new TicketQrAuthenticator(str_repeat('x', 32), 'test');
        foreach (['', 'a.b.c', 'not-a-token', str_repeat('x', 2049)] as $t) {
            self::assertNull($a->verify($t,'receipt',150));
        }
    }
}
