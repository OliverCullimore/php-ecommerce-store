<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class User
{
    public function __construct(public int $id, public string $email, public string $passwordHash)
    {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self((int) $r['id'], (string) $r['email'], (string) $r['password_hash']);
    }
}
