<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface as Response;
use Twig\Environment;

final class View
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(Response $response, string $template, array $data = []): Response
    {
        $data += [
            'flash' => Flash::pull(),
            'csrf' => Csrf::token(),
            'admin_email' => $_SESSION['admin_email'] ?? null,
            'customer_name' => $_SESSION['customer_name'] ?? null,
            'cart_count' => Cart::count(),
        ];
        $response->getBody()->write($this->twig->render($template, $data));

        return $response;
    }
}
