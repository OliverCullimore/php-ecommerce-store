<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class Customer
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $passwordHash,
        public ?string $notes,
    ) {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self((int) $r['id'], (string) $r['name'], (string) $r['email'], $r['phone'] ?? null, $r['address'] ?? null, $r['password_hash'] ?? null, $r['notes'] ?? null);
    }

    public function isRegistered(): bool
    {
        return $this->passwordHash !== null;
    }
}
