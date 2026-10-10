<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class Supplier
{
    public function __construct(public int $id, public string $name, public ?string $contactInfo)
    {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self((int) $r['id'], (string) $r['name'], $r['contact_info'] ?? null);
    }
}
