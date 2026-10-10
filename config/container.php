<?php

declare(strict_types=1);

use App\Domain\Invoice\InvoiceRenderer;
use App\Domain\Payment\CheckoutService;
use App\Domain\Payment\FakePaymentGateway;
use App\Domain\Payment\GlobalPaymentsGateway;
use App\Domain\Payment\PaymentGatewayInterface;
use App\Repository\CatalogueRepository;
use App\Repository\CustomerRepository;
use App\Repository\PaymentRepository;
use App\Repository\SalesOrderRepository;
use App\Support\TwigFactory;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;

/**
 * @param array<string, mixed> $overrides definition overrides, e.g. PaymentGatewayInterface::class => new FakePaymentGateway() in tests
 * @return ContainerInterface
 */
return static function (string $root, array $overrides = []): ContainerInterface {
    $builder = new ContainerBuilder();
    $builder->addDefinitions([
        'settings' => static fn (): array => require $root . '/config/settings.php',

        Connection::class => static function (ContainerInterface $c): Connection {
            $db = $c->get('settings')['db'];

            return DriverManager::getConnection([
                'driver' => 'pdo_mysql',
                'host' => $db['host'],
                'port' => $db['port'],
                'dbname' => $db['name'],
                'user' => $db['user'],
                'password' => $db['pass'],
                'charset' => 'utf8mb4',
            ]);
        },

        LoggerInterface::class => static function () use ($root): LoggerInterface {
            return new Logger('app', [new StreamHandler($root . '/var/app.log')]);
        },

        Environment::class => static function (ContainerInterface $c) use ($root): Environment {
            $s = $c->get('settings');

            return TwigFactory::create($root . '/templates', $s['app']['debug'] ? null : $root . '/var/twig', $s['app']['debug'], ['shop_name' => $s['shop']['name']]);
        },

        PaymentGatewayInterface::class => static function (ContainerInterface $c): PaymentGatewayInterface {
            $p = $c->get('settings')['payment'];

            return match ($p['gateway']) {
                'globalpayments' => new GlobalPaymentsGateway($p['gp']['merchant_id'], $p['gp']['account'], $p['gp']['secret'], $p['gp']['hpp_url'], $p['gp']['api_url'], $p['gp']['refund_password']),
                'fake' => new FakePaymentGateway(),
                default => throw new RuntimeException('Unknown PAYMENT_GATEWAY: ' . $p['gateway']),
            };
        },

        CheckoutService::class => static function (ContainerInterface $c): CheckoutService {
            return new CheckoutService(
                $c->get(PaymentGatewayInterface::class),
                $c->get(SalesOrderRepository::class),
                $c->get(PaymentRepository::class),
                $c->get(CatalogueRepository::class),
                $c->get(\App\Domain\Order\OrderService::class),
                $c->get('settings')['app']['url'],
            );
        },

        InvoiceRenderer::class => static function (ContainerInterface $c): InvoiceRenderer {
            $shop = $c->get('settings')['shop'];

            return new InvoiceRenderer(
                $c->get(SalesOrderRepository::class),
                $c->get(CustomerRepository::class),
                $c->get(PaymentRepository::class),
                $c->get(Environment::class),
                $shop['name'],
                $shop['address'],
            );
        },
    ]);
    $builder->addDefinitions($overrides);

    return $builder->build();
};
