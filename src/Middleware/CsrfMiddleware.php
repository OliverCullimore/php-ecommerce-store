<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $unsafe = !in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true);
        if ($unsafe && !str_starts_with($request->getUri()->getPath(), '/webhook/')) {
            $body = $request->getParsedBody();
            $submitted = is_array($body) ? ($body['_csrf'] ?? null) : null;
            $submitted ??= $request->getHeaderLine('X-CSRF-Token') ?: null;
            if (!Csrf::valid(is_string($submitted) ? $submitted : null)) {
                $res = new Response(403);
                $res->getBody()->write('Invalid or missing CSRF token. Go back, refresh the page and try again.');

                return $res;
            }
        }

        return $handler->handle($request);
    }
}
