<?php

declare(strict_types=1);

namespace App\Controller\Webhook;

use App\Domain\Payment\CheckoutService;
use App\Domain\Payment\InvalidCallbackException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

final class PaymentWebhookController
{
    public function __construct(private readonly CheckoutService $checkout, private readonly LoggerInterface $logger)
    {
    }

    public function handle(Request $req, Response $res): Response
    {
        $payload = $req->getMethod() === 'POST' ? (array) $req->getParsedBody() : $req->getQueryParams();
        try {
            $orderId = $this->checkout->handleCallback($payload);
        } catch (InvalidCallbackException $e) {
            $this->logger->warning('Rejected payment callback', ['reason' => $e->getMessage()]);

            return $res->withStatus(400);
        } catch (\Throwable $e) {
            $this->logger->error('Payment callback failed', ['exception' => $e]);

            return $res->withStatus(500);
        }

        // The hosted page shows our response inside its iframe, so break out to the order page.
        $url = htmlspecialchars('/checkout/return/' . $orderId, ENT_QUOTES);
        $res->getBody()->write('<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $url . '"><script>window.top.location.href="' . $url . '"</script><a href="' . $url . '" target="_top">Continue</a>');

        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
