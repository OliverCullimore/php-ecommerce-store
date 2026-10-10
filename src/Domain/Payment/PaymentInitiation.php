<?php

declare(strict_types=1);

namespace App\Domain\Payment;

/** Describes the browser hand-off: POST `fields` to `actionUrl` (hosted iframe/redirect page). */
final readonly class PaymentInitiation
{
    /** @param array<string, string> $fields */
    public function __construct(public string $actionUrl, public array $fields, public string $reference)
    {
    }
}
