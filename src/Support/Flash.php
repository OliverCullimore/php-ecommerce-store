<?php

declare(strict_types=1);

namespace App\Support;

final class Flash
{
    public static function success(string $message): void
    {
        $_SESSION['_flash'][] = ['type' => 'success', 'text' => $message];
    }

    public static function error(string $message): void
    {
        $_SESSION['_flash'][] = ['type' => 'error', 'text' => $message];
    }

    /** @return list<array{type: string, text: string}> */
    public static function pull(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $messages;
    }
}
