<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testItAddsSecurityHeadersToMainResponses(): void
    {
        $subscriber = new SecurityHeadersSubscriber();
        $response = new Response('<html lang="sv"></html>');
        $event = new ResponseEvent(
            new NoopKernel(),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $subscriber->onKernelResponse($event);

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertStringContainsString('geolocation=()', (string) $response->headers->get('Permissions-Policy'));
    }

    public function testItAddsHstsOnlyForSecureRequests(): void
    {
        $subscriber = new SecurityHeadersSubscriber();
        $secureResponse = new Response('ok');
        $secureEvent = new ResponseEvent(
            new NoopKernel(),
            Request::create('https://driftpunkt.test/'),
            HttpKernelInterface::MAIN_REQUEST,
            $secureResponse,
        );
        $plainResponse = new Response('ok');
        $plainEvent = new ResponseEvent(
            new NoopKernel(),
            Request::create('http://driftpunkt.test/'),
            HttpKernelInterface::MAIN_REQUEST,
            $plainResponse,
        );

        $subscriber->onKernelResponse($secureEvent);
        $subscriber->onKernelResponse($plainEvent);

        self::assertSame('max-age=31536000; includeSubDomains', $secureResponse->headers->get('Strict-Transport-Security'));
        self::assertFalse($plainResponse->headers->has('Strict-Transport-Security'));
    }
}

final class NoopKernel implements HttpKernelInterface
{
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        return new Response();
    }
}
