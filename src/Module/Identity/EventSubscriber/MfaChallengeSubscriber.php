<?php

declare(strict_types=1);

namespace App\Module\Identity\EventSubscriber;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Service\MfaPolicyResolver;
use App\Module\Identity\Service\MfaSessionManager;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class MfaChallengeSubscriber implements EventSubscriberInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MfaPolicyResolver $mfaPolicyResolver,
        private readonly MfaSessionManager $mfaSessionManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -10],
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

        if (!$user instanceof User || !$request->hasSession()) {
            return;
        }

        if ($token instanceof TestBrowserToken) {
            return;
        }

        if (!$this->mfaPolicyResolver->requiresMfa($user) || $this->mfaSessionManager->isVerified($request->getSession(), $user)) {
            return;
        }

        if ($this->shouldBypass($request->getPathInfo(), (string) $request->attributes->get('_route', ''))) {
            return;
        }

        if (str_starts_with($request->getPathInfo(), '/portal')) {
            $this->saveTargetPath($request->getSession(), 'main', $request->getUri());
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_mfa_challenge')));
    }

    private function shouldBypass(string $path, string $route): bool
    {
        if (str_starts_with($path, '/_wdt') || str_starts_with($path, '/_profiler')) {
            return true;
        }

        if (\in_array($route, [
            'app_mfa_challenge',
            'app_logout',
            'app_home',
            'app_news_index',
            'app_news_show',
            'app_public_search',
            'app_contact',
            'app_status_page',
            'app_knowledge_base_public',
            'app_cookie_policy',
            'app_privacy_policy',
            'app_terms_page',
            'app_locale_switch',
        ], true)) {
            return true;
        }

        return \in_array($path, [
            '/',
            '/nyheter',
            '/sok',
            '/kontakta-oss',
            '/driftstatus',
            '/kunskapsbas',
            '/cookiepolicy',
            '/integritetspolicy',
            '/anvandarvillkor',
            '/mfa',
            '/logout',
        ], true) || str_starts_with($path, '/nyheter/');
    }
}
