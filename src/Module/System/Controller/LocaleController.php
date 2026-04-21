<?php

declare(strict_types=1);

namespace App\Module\System\Controller;

use App\Module\System\Locale\AppLocale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    private const SESSION_KEY = 'app.locale';

    #[Route('/sprak/{locale}', name: 'app_locale_switch', methods: ['GET'])]
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $locale = AppLocale::normalize($locale);
        $request->getSession()->set(self::SESSION_KEY, $locale);

        $returnTo = trim((string) $request->query->get('returnTo'));
        if ('' !== $returnTo && str_starts_with($returnTo, '/')) {
            return $this->redirect($returnTo);
        }

        $referer = trim((string) $request->headers->get('referer'));
        if ('' !== $referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_home');
    }
}
