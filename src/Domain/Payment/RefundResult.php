<?php

declare(strict_types=1);

namespace App\Domain\Payment;

final readonly class RefundResult
{
    public function __construct(public bool $success, public string $reference, public string $message = '')
    {
    }
}
