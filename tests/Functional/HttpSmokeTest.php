<?php

declare(strict_types=1);

namespace Tests\Functional;

final class HttpSmokeTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testAdminRedirectsToLoginWhenSignedOut(): void
    {
        $res = $this->request('GET', '/admin/orders');
        $this->assertSame(303, $res->getStatusCode());
        $this->assertSame('/admin/login', $res->getHeaderLine('Location'));
    }

    public function testUnsafeRequestsWithoutCsrfTokenAreRejected(): void
    {
        $res = $this->request('POST', '/cart/add', ['variant_id' => 1]);
        $this->assertSame(403, $res->getStatusCode());
    }

    public function testWebhookRejectsCallbackWithoutReference(): void
    {
        $res = $this->request('POST', '/webhook/payment', []);
        $this->assertSame(400, $res->getStatusCode());
    }

    public function testAdminLoginPageRenders(): void
    {
        $res = $this->request('GET', '/admin/login');
        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('Admin sign in', (string) $res->getBody());
    }
}
