<?php

declare(strict_types=1);

namespace App\Module\Maintenance\EventSubscriber;

use App\Module\Identity\Entity\User;
use App\Module\Maintenance\Service\MaintenanceMode;
use App\Module\Maintenance\Service\MaintenanceNoticeProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

final class MaintenanceModeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MaintenanceMode $maintenanceMode,
        private readonly Security $security,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly Environment $twig,
        private readonly MaintenanceNoticeProvider $maintenanceNoticeProvider,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $state = $this->maintenanceMode->getState();

        if (!$event->isMainRequest() || !$state['effectiveEnabled']) {
            return;
        }

        $request = $event->getRequest();
        $this->logoutBlockedUser($request);

        if ($this->shouldBypass($request)) {
            return;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $content = $this->twig->render('maintenance/active.html.twig', [
            'message' => $this->maintenanceMode->getMessage(),
            'notice' => $this->maintenanceNoticeProvider->getNotice(),
        ]);

        $event->setResponse(new Response($content, Response::HTTP_SERVICE_UNAVAILABLE));
    }

    private function logoutBlockedUser(Request $request): void
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$user instanceof User || \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $this->tokenStorage->setToken(null);

        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }
    }

    private function shouldBypass(Request $request): bool
    {
        $route = (string) $request->attributes->get('_route', '');
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/_wdt') || str_starts_with($path, '/_profiler')) {
            return true;
        }

        if ('/login' === $path || 'app_login' === $route) {
            return true;
        }

        if (
            '/forgot-password' === $path
            || str_starts_with($path, '/reset-password/')
            || '/logout' === $path
            || str_starts_with($path, '/portal/admin')
            || '/' === $path
            || '/sok' === $path
            || '/kontakta-oss' === $path
            || '/driftstatus' === $path
        ) {
            return true;
        }

        return \in_array($route, [
            'app_home',
            'app_public_search',
            'app_password_reset_request',
            'app_password_reset_confirm',
            'app_logout',
            'app_portal_admin',
            'app_contact',
            'app_status_page',
        ], true);
    }
}
