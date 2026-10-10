<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class Item
{
    public function __construct(public int $id, public string $name, public ?string $description, public bool $active, public ?string $notes)
    {
    }

    /** @param array<string, mixed> $r */
    public static function fromRow(array $r): self
    {
        return new self((int) $r['id'], (string) $r['name'], $r['description'] ?? null, (bool) $r['active'], $r['notes'] ?? null);
    }
}
