<?php

namespace Tests\Unit;

use App\Support\OrderApi\RawBodyRequest;
use PHPUnit\Framework\TestCase;

/**
 * PHP 8.4 + Symfony 7.4 consume multipart PUT bodies (request_parse_body), which broke every signed QR upload over
 * real HTTP while feature tests (injected bodies) passed. Signed Order API multipart PUTs must keep the raw body.
 */
class RawBodyRequestTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        parent::tearDown();
    }

    public function test_order_api_multipart_put_keeps_the_exact_raw_body(): void
    {
        $raw = "--b\r\nContent-Disposition: form-data; name=\"expected_revision\"\r\n\r\n3\r\n--b--\r\n";
        $this->fake('PUT', '/integrations/qammaris-app/orders/v1/orders/01M4F6YYC3RCJVGVS6GCKYP7V6/jnt/qr?x=1', 'multipart/form-data; boundary=b');

        $request = RawBodyRequest::capture(fn () => $raw);

        $this->assertSame($raw, $request->getContent());
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('/integrations/qammaris-app/orders/v1/orders/01M4F6YYC3RCJVGVS6GCKYP7V6/jnt/qr?x=1', $request->getRequestUri());
        $this->assertSame('multipart/form-data; boundary=b', $request->header('Content-Type'));
    }

    public function test_everything_else_uses_the_normal_capture(): void
    {
        $never = function (): string {
            $this->fail('Raw reader must not be used outside signed Order API multipart PUT/PATCH');
        };
        foreach ([
            ['POST', '/integrations/qammaris-app/orders/v1/orders/x/claims', 'multipart/form-data; boundary=b'],
            ['PUT', '/integrations/qammaris-app/orders/v1/orders/x/costs/e', 'application/json'],
            ['PUT', '/admin/products/1', 'multipart/form-data; boundary=b'],
        ] as [$method, $uri, $type]) {
            $this->fake($method, $uri, $type);
            $this->assertSame($method, RawBodyRequest::capture($never)->getMethod());
        }
    }

    private function fake(string $method, string $uri, string $contentType): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['CONTENT_TYPE'] = $contentType;
        $_SERVER['HTTP_HOST'] = '127.0.0.1';
    }
}
