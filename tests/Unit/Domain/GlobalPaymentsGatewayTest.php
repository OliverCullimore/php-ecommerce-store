<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Payment\FakePaymentGateway;
use App\Domain\Payment\GlobalPaymentsGateway;
use App\Domain\Payment\InvalidCallbackException;
use App\Domain\Payment\PaymentRequest;
use PHPUnit\Framework\TestCase;

final class GlobalPaymentsGatewayTest extends TestCase
{
    private function gateway(?callable $http = null): GlobalPaymentsGateway
    {
        return new GlobalPaymentsGateway('merch', 'internet', 'secret', 'https://hpp.test', 'https://api.test', 'refundpw', $http);
    }

    /** @param array<string, string> $f */
    private function signedCallback(array $f): array
    {
        $parts = [$f['TIMESTAMP'], $f['MERCHANT_ID'], $f['ORDER_ID'], $f['RESULT'], $f['MESSAGE'], $f['PASREF'], $f['AUTHCODE']];
        $f['SHA1HASH'] = sha1(sha1(implode('.', $parts)) . '.secret');

        return $f;
    }

    private function callback(string $result = '00'): array
    {
        return $this->signedCallback(['TIMESTAMP' => '20261010120000', 'MERCHANT_ID' => 'merch', 'ORDER_ID' => 'FSM7-ab', 'RESULT' => $result, 'MESSAGE' => 'OK', 'PASREF' => 'P1', 'AUTHCODE' => 'A1']);
    }

    public function testInitiateSignsRequest(): void
    {
        $i = $this->gateway()->initiate(new PaymentRequest(7, 1999, 'GBP', 'FSM7-ab', '/r', '/cb', 'a@b.c'));
        $expected = sha1(sha1(implode('.', [$i->fields['TIMESTAMP'], 'merch', 'FSM7-ab', '1999', 'GBP'])) . '.secret');
        $this->assertSame($expected, $i->fields['SHA1HASH']);
        $this->assertSame('https://hpp.test', $i->actionUrl);
    }

    public function testValidCallbackIsAccepted(): void
    {
        $r = $this->gateway()->handleCallback($this->callback());
        $this->assertTrue($r->success);
        $this->assertSame('FSM7-ab|P1|A1', $r->gatewayReference);
    }

    public function testDeclinedCallbackIsNotSuccess(): void
    {
        $this->assertFalse($this->gateway()->handleCallback($this->callback('101'))->success);
    }

    public function testTamperedCallbackIsRejected(): void
    {
        $cb = $this->callback();
        $cb['RESULT'] = '00';
        $cb['MESSAGE'] = 'changed';
        $this->expectException(InvalidCallbackException::class);
        $this->gateway()->handleCallback($cb);
    }

    public function testRefundParsesSuccess(): void
    {
        $g = $this->gateway(static fn (string $url, string $xml): string => '<response><result>00</result><message>OK</message><pasref>P2</pasref><authcode>A2</authcode></response>');
        $r = $g->refund('FSM7-ab|P1|A1', 500, 'GBP');
        $this->assertTrue($r->success);
        $this->assertSame('FSM7-ab|P2|A2', $r->reference);
    }

    public function testRefundSurfacesGatewayRefusal(): void
    {
        $g = $this->gateway(static fn (string $url, string $xml): string => '<response><result>508</result><message>Too much</message></response>');
        $this->assertFalse($g->refund('FSM7-ab|P1|A1', 500, 'GBP')->success);
    }

    public function testFakeGatewayRecordsRefunds(): void
    {
        $f = new FakePaymentGateway();
        $this->assertTrue($f->refund('ref', 100, 'GBP')->success);
        $this->assertCount(1, $f->refunds);
    }
}
