<?php

declare(strict_types=1);

namespace App\Module\PublicPortal\Controller;

use App\Module\System\Service\SystemSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CookiePolicyController extends AbstractController
{
    public function __construct(
        private readonly SystemSettings $systemSettings,
    ) {
    }

    #[Route('/cookiepolicy', name: 'app_cookie_policy', methods: ['GET'])]
    public function __invoke(): Response
    {
        $cookiePolicySettings = $this->systemSettings->getCookiePolicySettings();

        if (
            $cookiePolicySettings['externalEnabled']
            && '' !== $cookiePolicySettings['externalUrl']
            && false !== filter_var($cookiePolicySettings['externalUrl'], \FILTER_VALIDATE_URL)
        ) {
            return new RedirectResponse($cookiePolicySettings['externalUrl']);
        }

        return $this->render('public/cookie_policy.html.twig', [
            'cookiePolicySettings' => $cookiePolicySettings,
        ]);
    }
}
