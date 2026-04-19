<?php

declare(strict_types=1);

namespace App\Module\PublicPortal\Controller;

use App\Module\System\Service\SystemSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly SystemSettings $systemSettings,
    ) {
    }

    #[Route('/kontakta-oss', name: 'app_contact', methods: ['GET'])]
    public function __invoke(): Response
    {
        $knowledgeBaseSettings = $this->systemSettings->getKnowledgeBaseSettings();

        return $this->render('public/contact.html.twig', [
            'contactPageSettings' => $this->systemSettings->getContactPageSettings(),
            'knowledgeBaseSettings' => $knowledgeBaseSettings,
            'homeSupportWidget' => $this->filterHomeSupportWidget(
                $this->systemSettings->getHomeSupportWidgetSettings(),
                (bool) $knowledgeBaseSettings['publicEnabled'],
            ),
        ]);
    }

    /**
     * @param array{
     *     title: string,
     *     intro: string,
     *     links: list<array{icon: string, title: string, url: string}>,
     *     linkLines: list<string>
     * } $widget
     * @return array{
     *     title: string,
     *     intro: string,
     *     links: list<array{icon: string, title: string, url: string}>,
     *     linkLines: list<string>
     * }
     */
    private function filterHomeSupportWidget(array $widget, bool $publicKnowledgeBaseEnabled): array
    {
        if ($publicKnowledgeBaseEnabled) {
            return $widget;
        }

        $widget['links'] = array_values(array_filter(
            $widget['links'],
            fn (array $item): bool => !$this->isPublicKnowledgeBaseUrl($item['url']),
        ));
        $widget['linkLines'] = array_values(array_filter(
            $widget['linkLines'],
            fn (string $line): bool => !$this->isPublicKnowledgeBaseUrl(trim((string) (explode('|', $line, 3)[2] ?? ''))),
        ));

        return $widget;
    }

    private function isPublicKnowledgeBaseUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        $path = \is_string($path) ? $path : $url;

        return str_starts_with($path, '/kunskapsbas');
    }
}
