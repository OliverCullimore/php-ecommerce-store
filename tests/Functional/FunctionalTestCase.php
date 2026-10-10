<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Bootstrap;
use App\Domain\Payment\FakePaymentGateway;
use App\Domain\Payment\PaymentGatewayInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

abstract class FunctionalTestCase extends TestCase
{
    protected FakePaymentGateway $gateway;

    /** @param array<string, mixed>|null $form */
    protected function request(string $method, string $path, ?array $form = null): ResponseInterface
    {
        $this->gateway = new FakePaymentGateway();
        $app = Bootstrap::createApp(dirname(__DIR__, 2), [PaymentGatewayInterface::class => $this->gateway]);
        $req = (new ServerRequestFactory())->createServerRequest($method, $path);
        if ($form !== null) {
            $req = $req->withParsedBody($form);
        }

        return $app->handle($req);
    }
}
