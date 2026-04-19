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
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('maintenance_notice', [$this->runtime, 'maintenanceNotice']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('article_body', [$this->runtime, 'renderArticleBody'], ['is_safe' => ['html']]),
        ];
    }
}
