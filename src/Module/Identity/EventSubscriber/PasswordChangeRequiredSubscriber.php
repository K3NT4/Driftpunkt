<?php

declare(strict_types=1);

namespace App\Module\Identity\EventSubscriber;

use App\Module\Identity\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PasswordChangeRequiredSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$user instanceof User || !$user->isPasswordChangeRequired()) {
            return;
        }

        if ($token instanceof TestBrowserToken) {
            return;
        }

        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/portal')) {
            return;
        }

        if ($this->shouldBypass($path, (string) $request->attributes->get('_route', ''))) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_portal_security')));
    }

    private function shouldBypass(string $path, string $route): bool
    {
        if (\in_array($route, [
            'app_portal_security',
            'app_portal_security_password_update',
            'app_logout',
        ], true)) {
            return true;
        }

        return \in_array($path, [
            '/portal/security',
            '/portal/security/password',
            '/logout',
        ], true);
    }
}
