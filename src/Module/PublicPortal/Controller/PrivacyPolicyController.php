<?php

declare(strict_types=1);

namespace App\Module\PublicPortal\Controller;

use App\Module\System\Service\SystemSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrivacyPolicyController extends AbstractController
{
    public function __construct(
        private readonly SystemSettings $systemSettings,
    ) {
    }

    #[Route('/integritetspolicy', name: 'app_privacy_policy', methods: ['GET'])]
    public function __invoke(): Response
    {
        $privacyPolicySettings = $this->systemSettings->getPrivacyPolicySettings();

        if (
            $privacyPolicySettings['externalEnabled']
            && '' !== $privacyPolicySettings['externalUrl']
            && false !== filter_var($privacyPolicySettings['externalUrl'], \FILTER_VALIDATE_URL)
        ) {
            return new RedirectResponse($privacyPolicySettings['externalUrl']);
        }

        return $this->render('public/privacy_policy.html.twig', [
            'privacyPolicySettings' => $privacyPolicySettings,
        ]);
    }
}
