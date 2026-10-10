<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly bool $secureCookie)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (PHP_SAPI === 'cli') {
            // Tests and CLI tooling: keep an in-memory session instead of sending cookies.
            $_SESSION ??= [];

            return $handler->handle($request);
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_name('fsm_session');
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $this->secureCookie, 'path' => '/']);
            session_start();
        }
        $response = $handler->handle($request);
        session_write_close();

        return $response;
    }
}
