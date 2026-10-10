<?php

declare(strict_types=1);

namespace App\Domain\Payment;

/**
 * Global Payments (Realex) Hosted Payment Page + Remote API refunds.
 * Hash formats follow the public HPP/Remote integration guides; verify against
 * the sandbox before taking real money. gateway_reference is stored as
 * "ORDERID|PASREF|AUTHCODE" because the rebate call needs all three.
 */
final class GlobalPaymentsGateway implements PaymentGatewayInterface
{
    /** @param callable(string, string): string|null $http POST (url, xml body) => response body */
    public function __construct(
        private readonly string $merchantId,
        private readonly string $account,
        private readonly string $sharedSecret,
        private readonly string $hppUrl,
        private readonly string $apiUrl,
        private readonly string $refundPassword,
        private $http = null,
    ) {
    }

    public function name(): string
    {
        return 'globalpayments';
    }

    public function initiate(PaymentRequest $request): PaymentInitiation
    {
        $ts = date('YmdHis');
        $amount = (string) $request->amountPence;
        $fields = [
            'TIMESTAMP' => $ts,
            'MERCHANT_ID' => $this->merchantId,
            'ACCOUNT' => $this->account,
            'ORDER_ID' => $request->reference,
            'AMOUNT' => $amount,
            'CURRENCY' => $request->currency,
            'AUTO_SETTLE_FLAG' => '1',
            'COMMENT1' => (string) $request->orderId,
            'MERCHANT_RESPONSE_URL' => $request->callbackUrl,
            'HPP_CUSTOMER_EMAIL' => $request->customerEmail,
            'SHA1HASH' => $this->sign([$ts, $this->merchantId, $request->reference, $amount, $request->currency]),
        ];

        return new PaymentInitiation($this->hppUrl, $fields, $request->reference);
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $f = static fn (string $k): string => (string) ($payload[$k] ?? '');
        $expected = $this->sign([$f('TIMESTAMP'), $f('MERCHANT_ID'), $f('ORDER_ID'), $f('RESULT'), $f('MESSAGE'), $f('PASREF'), $f('AUTHCODE')]);
        if ($f('ORDER_ID') === '' || !hash_equals($expected, strtolower($f('SHA1HASH')))) {
            throw new InvalidCallbackException('Signature mismatch.');
        }
        $ok = $f('RESULT') === '00';
        $safe = array_diff_key($payload, ['SHA1HASH' => 1]);

        return new PaymentResult($f('ORDER_ID'), implode('|', [$f('ORDER_ID'), $f('PASREF'), $f('AUTHCODE')]), $ok, $f('MESSAGE'), $safe);
    }

    public function refund(string $gatewayReference, int $amountPence, string $currency): RefundResult
    {
        [$orderId, $pasref, $authcode] = array_pad(explode('|', $gatewayReference), 3, '');
        $ts = date('YmdHis');
        $amount = (string) $amountPence;
        $hash = $this->sign([$ts, $this->merchantId, $orderId, $amount, $currency, '']);
        $xml = sprintf(
            '<request type="rebate" timestamp="%s"><merchantid>%s</merchantid><account>%s</account><orderid>%s</orderid><pasref>%s</pasref><authcode>%s</authcode><amount currency="%s">%s</amount><refundhash>%s</refundhash><sha1hash>%s</sha1hash></request>',
            $ts,
            htmlspecialchars($this->merchantId, ENT_XML1),
            htmlspecialchars($this->account, ENT_XML1),
            htmlspecialchars($orderId, ENT_XML1),
            htmlspecialchars($pasref, ENT_XML1),
            htmlspecialchars($authcode, ENT_XML1),
            $currency,
            $amount,
            sha1($this->refundPassword),
            $hash,
        );

        try {
            $body = ($this->http ?? $this->post(...))($this->apiUrl, $xml);
            $res = simplexml_load_string($body);
        } catch (\Throwable $e) {
            return new RefundResult(false, '', 'Gateway unreachable: ' . $e->getMessage());
        }
        if ($res === false) {
            return new RefundResult(false, '', 'Unreadable gateway response.');
        }

        return (string) $res->result === '00'
            ? new RefundResult(true, $orderId . '|' . (string) $res->pasref . '|' . (string) $res->authcode, (string) $res->message)
            : new RefundResult(false, '', (string) $res->message ?: 'Refund declined.');
    }

    /** @param list<string> $parts */
    private function sign(array $parts): string
    {
        return sha1(sha1(implode('.', $parts)) . '.' . $this->sharedSecret);
    }

    private function post(string $url, string $body): string
    {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: text/xml\r\n", 'content' => $body, 'timeout' => 20]]);
        $out = @file_get_contents($url, false, $ctx);
        if ($out === false) {
            throw new \RuntimeException('HTTP request failed.');
        }

        return $out;
    }
}
