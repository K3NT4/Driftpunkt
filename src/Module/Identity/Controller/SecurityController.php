<?php

declare(strict_types=1);

namespace App\Module\Identity\Controller;

use App\Module\Identity\Entity\PasswordResetRequest;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Identity\Service\MfaPolicyResolver;
use App\Module\Identity\Service\MfaService;
use App\Module\Identity\Service\MfaSessionManager;
use App\Module\Identity\Service\PasswordResetMailer;
use App\Module\Identity\Service\PasswordResetService;
use App\Module\System\Service\SystemSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class SecurityController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(
        private readonly SystemSettings $systemSettings,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MfaPolicyResolver $mfaPolicyResolver,
        private readonly MfaService $mfaService,
        private readonly MfaSessionManager $mfaSessionManager,
        private readonly PasswordResetService $passwordResetService,
        private readonly PasswordResetMailer $passwordResetMailer,
    ) {
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $request->hasSession() && $this->mfaPolicyResolver->requiresMfa($currentUser) && !$this->mfaSessionManager->isVerified($request->getSession(), $currentUser)) {
            return $this->redirectToRoute('app_mfa_challenge');
        }

        if (null !== $currentUser) {
            return $this->redirectToRoute('app_home');
        }

        $role = $request->query->getString('role', 'customer');
        $allowedRoles = ['customer', 'technician', 'admin'];

        if (!\in_array($role, $allowedRoles, true)) {
            $role = 'customer';
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'loginRole' => $role,
            'customerLoginSettings' => $this->systemSettings->getCustomerLoginSettings(),
            'knowledgeBaseSettings' => $this->systemSettings->getKnowledgeBaseSettings(),
        ]);
    }

    #[Route('/forgot-password', name: 'app_password_reset_request', methods: ['GET', 'POST'])]
    public function requestPasswordReset(Request $request): Response|RedirectResponse
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $role = $this->resolveLoginRole($request);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('password_reset_request', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Kunde inte verifiera formuläret. Försök igen.');

                return $this->redirectToRoute('app_password_reset_request', ['role' => $role]);
            }

            $email = mb_strtolower(trim((string) $request->request->get('email')));

            if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'E-postadressen är inte giltig.');

                return $this->redirectToRoute('app_password_reset_request', ['role' => $role]);
            }

            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user instanceof User && $user->isActive()) {
                $token = $this->passwordResetService->createResetRequest($user);
                $resetUrl = $this->generateUrl('app_password_reset_confirm', [
                    'token' => $token,
                    'role' => $role,
                ], UrlGeneratorInterface::ABSOLUTE_URL);
                $this->passwordResetMailer->sendResetLink($user, $resetUrl);
            }

            $this->addFlash('success', 'Om adressen finns registrerad har vi skickat en länk för att återställa lösenordet.');

            return $this->redirectToRoute('app_password_reset_request', ['role' => $role]);
        }

        return $this->render('security/password_reset_request.html.twig', [
            'loginRole' => $role,
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_password_reset_confirm', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token): Response|RedirectResponse
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $role = $this->resolveLoginRole($request);
        $resetRequest = $this->passwordResetService->findActiveRequestByToken($token);

        if (!$resetRequest instanceof PasswordResetRequest) {
            $this->addFlash('error', 'Länken är ogiltig eller har gått ut. Begär en ny återställningslänk.');

            return $this->redirectToRoute('app_password_reset_request', ['role' => $role]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('password_reset_confirm', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Kunde inte verifiera formuläret. Försök igen.');

                return $this->redirectToRoute('app_password_reset_confirm', ['token' => $token, 'role' => $role]);
            }

            $password = (string) $request->request->get('password');
            $passwordConfirm = (string) $request->request->get('password_confirm');

            if (\strlen($password) < 12) {
                $this->addFlash('error', 'Lösenordet måste vara minst 12 tecken långt.');

                return $this->redirectToRoute('app_password_reset_confirm', ['token' => $token, 'role' => $role]);
            }

            if ($password !== $passwordConfirm) {
                $this->addFlash('error', 'Lösenorden matchar inte.');

                return $this->redirectToRoute('app_password_reset_confirm', ['token' => $token, 'role' => $role]);
            }

            $this->passwordResetService->resetPassword($resetRequest, $password);
            $this->addFlash('success', 'Ditt lösenord är uppdaterat. Du kan nu logga in.');

            return $this->redirectToRoute('app_login', ['role' => $role]);
        }

        return $this->render('security/password_reset_confirm.html.twig', [
            'loginRole' => $role,
            'token' => $token,
            'email' => $resetRequest->getUser()->getEmail(),
        ]);
    }

    #[Route('/register', name: 'app_register_customer', methods: ['GET', 'POST'])]
    public function registerCustomer(Request $request): Response|RedirectResponse
    {
        if (!$this->systemSettings->getCustomerLoginSettings()['createAccountEnabled']) {
            $this->addFlash('error', 'Självregistrering är inte aktiverad just nu.');

            return $this->redirectToRoute('app_login', ['role' => 'customer']);
        }

        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('customer_register', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Kunde inte verifiera registreringsformuläret.');

                return $this->redirectToRoute('app_register_customer');
            }

            $firstName = trim((string) $request->request->get('first_name'));
            $lastName = trim((string) $request->request->get('last_name'));
            $email = mb_strtolower(trim((string) $request->request->get('email')));
            $password = (string) $request->request->get('password');
            $passwordConfirm = (string) $request->request->get('password_confirm');
            $emailNotificationsEnabled = $request->request->getBoolean('email_notifications_enabled', true);

            if ('' === $firstName || '' === $lastName) {
                $this->addFlash('error', 'För- och efternamn måste anges.');

                return $this->redirectToRoute('app_register_customer');
            }

            if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'E-postadressen är inte giltig.');

                return $this->redirectToRoute('app_register_customer');
            }

            if (\strlen($password) < 12) {
                $this->addFlash('error', 'Lösenordet måste vara minst 12 tecken långt.');

                return $this->redirectToRoute('app_register_customer');
            }

            if ($password !== $passwordConfirm) {
                $this->addFlash('error', 'Lösenorden matchar inte.');

                return $this->redirectToRoute('app_register_customer');
            }

            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if (null !== $existingUser) {
                $this->addFlash('error', 'Det finns redan ett konto med den e-postadressen.');

                return $this->redirectToRoute('app_register_customer');
            }

            $user = new User($email, $firstName, $lastName, UserType::PRIVATE_CUSTOMER);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $emailNotificationsEnabled ? $user->enableEmailNotifications() : $user->disableEmailNotifications();

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Ditt konto skapades. Du kan nu logga in.');

            return $this->redirectToRoute('app_login', ['role' => 'customer']);
        }

        return $this->render('security/register.html.twig', [
            'customerLoginSettings' => $this->systemSettings->getCustomerLoginSettings(),
            'knowledgeBaseSettings' => $this->systemSettings->getKnowledgeBaseSettings(),
        ]);
    }

    #[Route('/mfa', name: 'app_mfa_challenge', methods: ['GET', 'POST'])]
    public function mfaChallenge(Request $request): Response|RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->mfaPolicyResolver->requiresMfa($user)) {
            return $this->redirectToRoute('app_portal_entry');
        }

        if ($request->hasSession() && $this->mfaSessionManager->isVerified($request->getSession(), $user)) {
            return $this->redirectToRoute('app_portal_entry');
        }

        $issuer = $this->resolveMfaIssuerName();
        $this->mfaService->ensureSecret($user);
        $this->entityManager->flush();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mfa_challenge', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Kunde inte verifiera MFA-formuläret. Försök igen.');

                return $this->redirectToRoute('app_mfa_challenge');
            }

            $code = (string) $request->request->get('code');
            if (!$this->mfaService->verifyCode($user, $code)) {
                $this->addFlash('error', 'Koden är inte giltig. Kontrollera appen och försök igen.');

                return $this->redirectToRoute('app_mfa_challenge');
            }

            if ($request->hasSession()) {
                $session = $request->getSession();
                $this->mfaSessionManager->markVerified($session, $user);
                $targetPath = $this->getTargetPath($session, 'main');
                $this->removeTargetPath($session, 'main');

                if ($user->isPasswordChangeRequired()) {
                    return $this->redirectToRoute('app_portal_security');
                }

                return new RedirectResponse($targetPath ?? $this->generateUrl('app_portal_entry'));
            }

            if ($user->isPasswordChangeRequired()) {
                return $this->redirectToRoute('app_portal_security');
            }

            return $this->redirectToRoute('app_portal_entry');
        }

        return $this->render('security/mfa_challenge.html.twig', [
            'issuer' => $issuer,
            'qrCodeDataUri' => $this->mfaService->getQrCodeDataUri($user, $issuer),
            'manualEntryCode' => $this->mfaService->getFormattedManualEntryCode($user),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('This route is intercepted by the logout key on your firewall.');
    }

    private function resolveMfaIssuerName(): string
    {
        $siteBranding = $this->systemSettings->getSiteBrandingSettings();
        $name = trim((string) ($siteBranding['name'] ?? ''));

        return '' !== $name ? $name : 'Driftpunkt';
    }

    private function resolveLoginRole(Request $request): string
    {
        $role = $request->query->getString('role', 'customer');
        $allowedRoles = ['customer', 'technician', 'admin'];

        if (!\in_array($role, $allowedRoles, true)) {
            return 'customer';
        }

        return $role;
    }
}
