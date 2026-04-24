<?php

declare(strict_types=1);

namespace App\Module\Identity\Security;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Service\MfaPolicyResolver;
use App\Module\Identity\Service\MfaSessionManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MfaPolicyResolver $mfaPolicyResolver,
        private readonly MfaSessionManager $mfaSessionManager,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_portal_entry'));
        }

        $targetPath = null;
        if ($request->hasSession()) {
            $session = $request->getSession();
            $targetPath = $this->getTargetPath($session, 'main');
            $this->removeTargetPath($session, 'main');
            $this->mfaSessionManager->clear($session);
        }

        if ($this->mfaPolicyResolver->requiresMfa($user)) {
            return new RedirectResponse($this->urlGenerator->generate('app_mfa_challenge'));
        }

        if ($request->hasSession()) {
            $this->mfaSessionManager->markVerified($request->getSession(), $user);
        }

        if ($user->isPasswordChangeRequired()) {
            return new RedirectResponse($this->urlGenerator->generate('app_portal_security'));
        }

        return new RedirectResponse($targetPath ?? $this->urlGenerator->generate('app_portal_entry'));
    }
}
