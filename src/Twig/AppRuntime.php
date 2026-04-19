<?php

declare(strict_types=1);

namespace App\Twig;

use App\Module\Maintenance\Service\MaintenanceMode;
use App\Module\Maintenance\Service\MaintenanceNoticeProvider;

final class AppRuntime
{
    public function __construct(
        private readonly MaintenanceMode $maintenanceMode,
        private readonly MaintenanceNoticeProvider $maintenanceNoticeProvider,
    ) {
    }

    public function maintenanceMode(): MaintenanceMode
    {
        return $this->maintenanceMode;
    }

    /**
     * @return array{
     *     status: 'upcoming'|'active',
     *     eyebrow: string,
     *     title: string,
     *     summary: string,
     *     schedule: ?string,
     *     href: ?string,
     *     linkLabel: string
     * }|null
     */
    public function maintenanceNotice(): ?array
    {
        return $this->maintenanceNoticeProvider->getNotice();
    }

    public function renderArticleBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        if ('' === $body) {
            return '';
        }

        $lines = explode("\n", $body);
        $html = [];
        $paragraph = [];
        $listItems = [];
        $listType = null;
        $blockquote = [];

        $flushParagraph = function () use (&$html, &$paragraph): void {
            if ([] === $paragraph) {
                return;
            }

            $html[] = sprintf('<p>%s</p>', implode('<br>', array_map([$this, 'renderInlineArticleMarkup'], $paragraph)));
            $paragraph = [];
        };

        $flushList = function () use (&$html, &$listItems, &$listType): void {
            if ([] === $listItems || null === $listType) {
                return;
            }

            $tag = 'ol' === $listType ? 'ol' : 'ul';
            $items = array_map(
                fn (string $item): string => sprintf('<li>%s</li>', $this->renderInlineArticleMarkup($item)),
                $listItems,
            );

            $html[] = sprintf('<%1$s>%2$s</%1$s>', $tag, implode('', $items));
            $listItems = [];
            $listType = null;
        };

        $flushBlockquote = function () use (&$html, &$blockquote): void {
            if ([] === $blockquote) {
                return;
            }

            $html[] = sprintf(
                '<blockquote><p>%s</p></blockquote>',
                implode('<br>', array_map([$this, 'renderInlineArticleMarkup'], $blockquote)),
            );
            $blockquote = [];
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ('' === $trimmed) {
                $flushParagraph();
                $flushList();
                $flushBlockquote();
                continue;
            }

            if ('---' === $trimmed) {
                $flushParagraph();
                $flushList();
                $flushBlockquote();
                $html[] = '<hr>';
                continue;
            }

            if (preg_match('/^(#{2,3})\s+(.+)$/', $trimmed, $matches) === 1) {
                $flushParagraph();
                $flushList();
                $flushBlockquote();
                $level = strlen($matches[1]);
                $html[] = sprintf('<h%d>%s</h%d>', $level, $this->renderInlineArticleMarkup($matches[2]), $level);
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $matches) === 1) {
                $flushParagraph();
                $flushList();
                $blockquote[] = $matches[1];
                continue;
            }

            if (preg_match('/^- (.+)$/', $trimmed, $matches) === 1) {
                $flushParagraph();
                $flushBlockquote();
                if ('ul' !== $listType) {
                    $flushList();
                    $listType = 'ul';
                }
                $listItems[] = $matches[1];
                continue;
            }

            if (preg_match('/^\d+\. (.+)$/', $trimmed, $matches) === 1) {
                $flushParagraph();
                $flushBlockquote();
                if ('ol' !== $listType) {
                    $flushList();
                    $listType = 'ol';
                }
                $listItems[] = $matches[1];
                continue;
            }

            $flushList();
            $flushBlockquote();
            $paragraph[] = $trimmed;
        }

        $flushParagraph();
        $flushList();
        $flushBlockquote();

        return implode("\n", $html);
    }

    private function renderInlineArticleMarkup(string $text): string
    {
        $text = htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        $patterns = [
            '/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/' => '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
            '/\*\*(.+?)\*\*/' => '<strong>$1</strong>',
            '/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/' => '<em>$1</em>',
            '/`([^`]+)`/' => '<code>$1</code>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }
}
