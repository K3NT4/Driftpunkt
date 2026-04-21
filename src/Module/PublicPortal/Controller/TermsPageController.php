<?php

declare(strict_types=1);

namespace App\Module\PublicPortal\Controller;

use App\Module\System\Service\SystemSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TermsPageController extends AbstractController
{
    public function __construct(
        private readonly SystemSettings $systemSettings,
    ) {
    }

    #[Route('/anvandarvillkor', name: 'app_terms_page', methods: ['GET'])]
    public function __invoke(): Response
    {
        $termsPageSettings = $this->systemSettings->getTermsPageSettings();

        if (
            $termsPageSettings['externalEnabled']
            && '' !== $termsPageSettings['externalUrl']
            && false !== filter_var($termsPageSettings['externalUrl'], \FILTER_VALIDATE_URL)
        ) {
            return new RedirectResponse($termsPageSettings['externalUrl']);
        }

        return $this->render('public/terms_page.html.twig', [
            'termsPageSettings' => $termsPageSettings,
        ]);
    }
}
