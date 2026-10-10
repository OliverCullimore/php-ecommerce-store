<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface as Response;

final class Redirect
{
    public static function to(Response $response, string $url, int $status = 303): Response
    {
        return $response->withHeader('Location', $url)->withStatus($status);
    }
}
