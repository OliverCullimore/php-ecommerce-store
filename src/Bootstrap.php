<?php

declare(strict_types=1);

namespace App;

use App\Middleware\CsrfMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

final class Bootstrap
{
    /**
     * @param array<string, mixed> $containerOverrides
     * @return App<\Psr\Container\ContainerInterface>
     */
    public static function createApp(string $root, array $containerOverrides = []): App
    {
        if (is_file($root . '/.env')) {
            Dotenv::createImmutable($root)->safeLoad();
        }

        $container = (require $root . '/config/container.php')($root, $containerOverrides);
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $settings = $container->get('settings');

        (require $root . '/config/routes.php')($app);

        // Added last = runs first: errors > security headers > session > body parsing > CSRF > routing.
        $app->addRoutingMiddleware();
        $app->add(new CsrfMiddleware());
        $app->addBodyParsingMiddleware();
        $app->add(new SessionMiddleware(str_starts_with($settings['app']['url'], 'https://')));
        $app->add(new SecurityHeadersMiddleware());
        $app->addErrorMiddleware($settings['app']['debug'], true, true, $container->get(LoggerInterface::class));

        return $app;
    }
}
