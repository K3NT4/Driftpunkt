<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly AppRuntime $runtime,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'maintenance_mode' => $this->runtime->maintenanceMode(),
            'site_branding' => $this->runtime->siteBranding(),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('maintenance_notice', [$this->runtime, 'maintenanceNotice']),
            new TwigFunction('t', [$this->runtime, 'translate']),
            new TwigFunction('available_locales', [$this->runtime, 'availableLocales']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('article_body', [$this->runtime, 'renderArticleBody'], ['is_safe' => ['html']]),
        ];
    }
}
