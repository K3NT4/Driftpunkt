<?php

declare(strict_types=1);

namespace App\Twig;

use App\Module\Maintenance\Service\MaintenanceMode;
use App\Module\Maintenance\Service\MaintenanceNoticeProvider;
use App\Module\System\Service\CodeUpdateManager;
use App\Module\System\Service\SystemSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

final class AppRuntime
{
    private const DRIFTPUNKT_PUBLIC_GITHUB_URL = 'https://github.com/K3NT4/Driftpunkt';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $translationFileCatalogues = [];

    public function __construct(
        private readonly MaintenanceMode $maintenanceMode,
        private readonly MaintenanceNoticeProvider $maintenanceNoticeProvider,
        private readonly SystemSettings $systemSettings,
        private readonly CodeUpdateManager $codeUpdateManager,
        private readonly RequestStack $requestStack,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
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

    /**
     * @return array{
     *     name: string,
     *     logoPath: string,
     *     footerText: string,
     *     appVersion: string,
     *     githubUrl: string,
     *     copyrightLabel: string
     * }
     */
    public function siteBranding(): array
    {
        $branding = $this->systemSettings->getSiteBrandingSettings();
        $application = $this->codeUpdateManager->describeCurrentApplication();

        return [
            'name' => $branding['name'],
            'logoPath' => $branding['logoPath'],
            'footerText' => $branding['footerText'],
            'appVersion' => (string) ($application['version'] ?? 'lokal'),
            'githubUrl' => self::DRIFTPUNKT_PUBLIC_GITHUB_URL,
            'copyrightLabel' => sprintf('© %s Driftpunkt', date('Y')),
        ];
    }

    /**
     * @param array<string, scalar|\Stringable|null> $parameters
     */
    public function translate(string $key, array $parameters = [], ?string $locale = null): string
    {
        $locale ??= $this->requestStack->getCurrentRequest()?->getLocale();
        $locale = \is_string($locale) && '' !== trim($locale) ? trim($locale) : 'sv';
        $catalogue = $this->loadTranslationCatalogue($locale);
        $message = $catalogue[$key] ?? null;

        if (!\is_string($message) || '' === $message) {
            $fallbackCatalogue = 'sv' === $locale ? $catalogue : $this->loadTranslationCatalogue('sv');
            $message = $fallbackCatalogue[$key] ?? $key;
        }

        foreach ($parameters as $parameter => $value) {
            $message = str_replace((string) $parameter, (string) $value, $message);
        }

        return $message;
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public function availableLocales(): array
    {
        return $this->systemSettings->getTranslationLocales();
    }

    /**
     * @return array{
     *     locales: list<array{code: string, name: string}>,
     *     selectedLocale: string,
     *     entries: list<array{
     *         key: string,
     *         base: string,
     *         translation: string,
     *         hasOverride: bool,
     *         isMissing: bool,
     *         isTranslated: bool,
     *         group: string,
     *         groupLabel: string,
     *         label: string,
     *         description: string
     *     }>,
     *     groups: list<array{code: string, label: string, count: int}>,
     *     summary: array{total: int, translated: int, missing: int, overrides: int},
     *     filters: array{query: string, group: string, state: string}
     * }
     */
    public function translationEditor(string $locale, string $query = '', string $group = 'all', string $state = 'all', int $page = 1, int $perPage = 10): array
    {
        $selectedLocale = trim($locale) !== '' ? trim($locale) : 'en';
        $selectedLocale = strtolower(str_replace('_', '-', $selectedLocale));
        $locales = $this->availableLocales();
        $query = trim($query);
        $group = '' !== trim($group) ? trim($group) : 'all';
        $state = '' !== trim($state) ? trim($state) : 'all';
        $page = max(1, $page);
        $allowedPerPage = [10, 25, 50, 100];
        if (!\in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $allKnownLocales = array_values(array_unique(array_merge(
            ['sv', 'en', $selectedLocale],
            array_map(static fn (array $entry): string => $entry['code'], $locales),
            array_keys($this->systemSettings->getTranslationOverrides()),
        )));

        $keys = [];
        foreach ($allKnownLocales as $knownLocale) {
            foreach (array_keys($this->loadTranslationFileCatalogue($knownLocale)) as $key) {
                if (\is_string($key) && '' !== trim($key)) {
                    $keys[$key] = true;
                }
            }
        }

        foreach (array_keys($this->systemSettings->getTranslationOverridesForLocale($selectedLocale)) as $key) {
            if (\is_string($key) && '' !== trim($key)) {
                $keys[$key] = true;
            }
        }

        $baseCatalogue = $this->loadTranslationCatalogue('sv');
        $selectedFileCatalogue = 'sv' === $selectedLocale ? $baseCatalogue : $this->loadTranslationFileCatalogue($selectedLocale);
        $selectedOverrides = $this->systemSettings->getTranslationOverridesForLocale($selectedLocale);
        $groupDefinitions = $this->translationGroupDefinitions();
        $entries = [];
        $sortedKeys = array_keys($keys);
        usort(
            $sortedKeys,
            static function (string $left, string $right): int {
                return strcmp($left, $right);
            },
        );

        foreach ($sortedKeys as $key) {
            $base = (string) ($baseCatalogue[$key] ?? $key);
            $translation = 'sv' === $selectedLocale
                ? (string) ($selectedOverrides[$key] ?? $base)
                : (string) ($selectedOverrides[$key] ?? $selectedFileCatalogue[$key] ?? '');
            $meta = $this->translationKeyMeta($key);
            $isMissing = '' === trim($translation);
            $isTranslated = !$isMissing;
            $searchHaystack = mb_strtolower(implode(' ', [
                $key,
                $meta['label'],
                $meta['description'],
                $base,
                $translation,
                $meta['groupLabel'],
            ]));

            if ('all' !== $group && $meta['group'] !== $group) {
                continue;
            }

            if ('' !== $query && !str_contains($searchHaystack, mb_strtolower($query))) {
                continue;
            }

            if ('missing' === $state && !$isMissing) {
                continue;
            }

            if ('translated' === $state && !$isTranslated) {
                continue;
            }

            if ('overrides' === $state && !isset($selectedOverrides[$key])) {
                continue;
            }

            $entries[] = [
                'key' => $key,
                'base' => $base,
                'translation' => $translation,
                'hasOverride' => isset($selectedOverrides[$key]),
                'isMissing' => $isMissing,
                'isTranslated' => $isTranslated,
                'group' => $meta['group'],
                'groupLabel' => $meta['groupLabel'],
                'label' => $meta['label'],
                'description' => $meta['description'],
            ];
        }

        usort(
            $entries,
            static function (array $left, array $right): int {
                if ($left['isMissing'] !== $right['isMissing']) {
                    return $left['isMissing'] ? -1 : 1;
                }

                $groupCompare = strcmp($left['groupLabel'], $right['groupLabel']);
                if (0 !== $groupCompare) {
                    return $groupCompare;
                }

                return strcmp($left['key'], $right['key']);
            },
        );

        $allEntries = [];
        foreach ($sortedKeys as $key) {
            $base = (string) ($baseCatalogue[$key] ?? $key);
            $translation = 'sv' === $selectedLocale
                ? (string) ($selectedOverrides[$key] ?? $base)
                : (string) ($selectedOverrides[$key] ?? $selectedFileCatalogue[$key] ?? '');
            $meta = $this->translationKeyMeta($key);
            $allEntries[] = [
                'group' => $meta['group'],
                'hasOverride' => isset($selectedOverrides[$key]),
                'isTranslated' => '' !== trim($translation),
            ];
        }

        $groups = [];
        foreach ($groupDefinitions as $groupCode => $definition) {
            $groupEntries = array_values(array_filter(
                $allEntries,
                static fn (array $entry): bool => $entry['group'] === $groupCode,
            ));
            $count = \count($groupEntries);

            if ($count <= 0) {
                continue;
            }

            $translatedCount = \count(array_filter($groupEntries, static fn (array $entry): bool => $entry['isTranslated']));
            $missingCount = \count(array_filter($groupEntries, static fn (array $entry): bool => !$entry['isTranslated']));
            $groups[] = [
                'code' => $groupCode,
                'label' => $definition['label'],
                'count' => $count,
                'missing' => $missingCount,
                'translated' => $translatedCount,
                'overrides' => \count(array_filter($groupEntries, static fn (array $entry): bool => $entry['hasOverride'])),
                'description' => $definition['description'],
                'completionPercent' => $count > 0 ? (int) round(($translatedCount / $count) * 100) : 0,
            ];
        }

        usort(
            $groups,
            static function (array $left, array $right): int {
                if ($left['missing'] !== $right['missing']) {
                    return $right['missing'] <=> $left['missing'];
                }

                return strcmp($left['label'], $right['label']);
            },
        );

        $recommendedGroup = null;
        foreach ($groups as $groupInfo) {
            if (($groupInfo['missing'] ?? 0) > 0) {
                $recommendedGroup = $groupInfo;
                break;
            }
        }

        $activeGroup = null;
        foreach ($groups as $groupInfo) {
            if (($groupInfo['code'] ?? null) === $group) {
                $activeGroup = $groupInfo;
                break;
            }
        }

        $nextMissingEntry = null;
        foreach ($entries as $entry) {
            if ($entry['isMissing']) {
                $nextMissingEntry = $entry;
                break;
            }
        }

        $filteredEntryCount = \count($entries);
        $pageCount = max(1, (int) ceil($filteredEntryCount / $perPage));
        $page = min($page, $pageCount);
        $offset = ($page - 1) * $perPage;
        $paginatedEntries = array_slice($entries, $offset, $perPage);

        return [
            'locales' => $locales,
            'selectedLocale' => $selectedLocale,
            'entries' => $paginatedEntries,
            'groups' => $groups,
            'summary' => [
                'total' => \count($allEntries),
                'translated' => \count(array_filter($allEntries, static fn (array $entry): bool => $entry['isTranslated'])),
                'missing' => \count(array_filter($allEntries, static fn (array $entry): bool => !$entry['isTranslated'])),
                'overrides' => \count(array_filter($allEntries, static fn (array $entry): bool => $entry['hasOverride'])),
            ],
            'workspace' => [
                'filteredTotal' => $filteredEntryCount,
                'page' => $page,
                'perPage' => $perPage,
                'pageCount' => $pageCount,
                'from' => $filteredEntryCount > 0 ? $offset + 1 : 0,
                'to' => min($filteredEntryCount, $offset + $perPage),
                'hasPreviousPage' => $page > 1,
                'hasNextPage' => $page < $pageCount,
                'completionPercent' => \count($allEntries) > 0
                    ? (int) round((\count(array_filter($allEntries, static fn (array $entry): bool => $entry['isTranslated'])) / \count($allEntries)) * 100)
                    : 0,
                'recommendedGroup' => $recommendedGroup,
                'activeGroup' => $activeGroup,
                'nextMissingEntry' => $nextMissingEntry,
                'isFocusedView' => '' === $query && 'missing' === $state,
                'batchSize' => min($perPage, max(1, $filteredEntryCount)),
            ],
            'filters' => [
                'query' => $query,
                'group' => $group,
                'state' => $state,
                'page' => $page,
                'perPage' => $perPage,
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    private function translationGroupDefinitions(): array
    {
        return [
            'global' => [
                'label' => 'Globalt',
                'description' => 'Språkval, branding och gemensamma element.',
            ],
            'navigation' => [
                'label' => 'Navigering',
                'description' => 'Huvudmeny och delade länkar.',
            ],
            'home' => [
                'label' => 'Startsida',
                'description' => 'Sök, hero, roller och nyheter.',
            ],
            'login' => [
                'label' => 'Login',
                'description' => 'Inloggning, hjälptexter och driftmeddelanden.',
            ],
            'contact' => [
                'label' => 'Kontakt',
                'description' => 'Kontakt- och supportflöden.',
            ],
            'status' => [
                'label' => 'Status',
                'description' => 'Statussidor, statuskort och driftinfo.',
            ],
            'news' => [
                'label' => 'Nyheter',
                'description' => 'Nyhetslistor och nyhetsdetaljer.',
            ],
            'other' => [
                'label' => 'Övrigt',
                'description' => 'Äldre eller blandade texter.',
            ],
        ];
    }

    /**
     * @return array{group: string, groupLabel: string, label: string, description: string}
     */
    private function translationKeyMeta(string $key): array
    {
        $group = match (true) {
            str_starts_with($key, 'ui.'), str_starts_with($key, 'brand.') => 'global',
            str_starts_with($key, 'nav.') => 'navigation',
            str_starts_with($key, 'home.') => 'home',
            str_starts_with($key, 'login.') => 'login',
            str_starts_with($key, 'contact.') => 'contact',
            str_starts_with($key, 'status.') => 'status',
            str_starts_with($key, 'news.') => 'news',
            default => 'other',
        };

        $definitions = $this->translationGroupDefinitions();
        $groupLabel = $definitions[$group]['label'] ?? 'Övrigt';

        $segments = explode('.', $key);
        $last = end($segments);
        $label = ucfirst(str_replace(['_', '-'], ' ', \is_string($last) ? $last : $key));

        $description = match ($key) {
            'ui.language' => 'Label för språkväxlaren.',
            'brand.logo_alt' => 'Alt-text för logotypen.',
            'nav.home' => 'Länk till startsidan.',
            'nav.portal' => 'Länk till portalen för inloggade användare.',
            'nav.login' => 'Knapp/länk för inloggning.',
            'nav.logout' => 'Knapp/länk för utloggning.',
            'nav.news' => 'Länk till nyhetssidan.',
            'nav.contact' => 'Länk till kontaktsidan.',
            'nav.status' => 'Länk till driftstatus.',
            'nav.support' => 'Länk eller etikett för support.',
            default => sprintf('%s · %s', $groupLabel, $key),
        };

        return [
            'group' => $group,
            'groupLabel' => $groupLabel,
            'label' => $label,
            'description' => $description,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadTranslationCatalogue(string $locale): array
    {
        $locale = strtolower(str_replace('_', '-', $locale));
        $baseCatalogue = array_map(
            static fn (mixed $value): string => \is_scalar($value) ? (string) $value : '',
            $this->loadTranslationFileCatalogue('sv'),
        );
        $baseOverrides = $this->systemSettings->getTranslationOverridesForLocale('sv');
        $baseCatalogue = array_replace($baseCatalogue, $baseOverrides);

        if ('sv' === $locale) {
            return $baseCatalogue;
        }

        $localeCatalogue = array_map(
            static fn (mixed $value): string => \is_scalar($value) ? (string) $value : '',
            $this->loadTranslationFileCatalogue($locale),
        );
        $localeOverrides = $this->systemSettings->getTranslationOverridesForLocale($locale);

        return array_replace($baseCatalogue, $localeCatalogue, $localeOverrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadTranslationFileCatalogue(string $locale): array
    {
        $locale = strtolower(str_replace('_', '-', $locale));

        if (isset($this->translationFileCatalogues[$locale])) {
            return $this->translationFileCatalogues[$locale];
        }

        $path = sprintf('%s/translations/messages.%s.yaml', $this->projectDir, $locale);
        if (!is_file($path)) {
            return $this->translationFileCatalogues[$locale] = [];
        }

        $data = Yaml::parseFile($path);
        if (!\is_array($data)) {
            return $this->translationFileCatalogues[$locale] = [];
        }

        return $this->translationFileCatalogues[$locale] = $data;
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
        $checklistItems = [];
        $blockquote = [];
        $calloutType = null;
        $calloutTitle = null;
        $calloutLines = [];
        $codeFenceLines = [];
        $insideCodeFence = false;

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

        $flushChecklist = function () use (&$html, &$checklistItems): void {
            if ([] === $checklistItems) {
                return;
            }

            $items = array_map(
                fn (array $item): string => sprintf(
                    '<li class="%s"><span class="article-checklist-box" aria-hidden="true">%s</span><span>%s</span></li>',
                    $item['checked'] ? 'is-done' : 'is-pending',
                    $item['checked'] ? '✓' : '',
                    $this->renderInlineArticleMarkup($item['label']),
                ),
                $checklistItems,
            );

            $html[] = sprintf('<ul class="article-checklist">%s</ul>', implode('', $items));
            $checklistItems = [];
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

        $flushCallout = function () use (&$html, &$calloutType, &$calloutTitle, &$calloutLines): void {
            if (null === $calloutType || [] === $calloutLines) {
                $calloutType = null;
                $calloutTitle = null;
                $calloutLines = [];

                return;
            }

            $bodyLines = array_values(array_filter(
                array_map(static fn (string $line): string => trim($line), $calloutLines),
                static fn (string $line): bool => '' !== $line,
            ));
            $title = null !== $calloutTitle && '' !== trim($calloutTitle)
                ? sprintf('<strong>%s</strong>', $this->renderInlineArticleMarkup($calloutTitle))
                : '';
            $bodyHtml = [] !== $bodyLines
                ? sprintf('<p>%s</p>', implode('<br>', array_map([$this, 'renderInlineArticleMarkup'], $bodyLines)))
                : '';

            $html[] = sprintf(
                '<div class="article-callout %s">%s%s</div>',
                $calloutType,
                $title,
                $bodyHtml,
            );

            $calloutType = null;
            $calloutTitle = null;
            $calloutLines = [];
        };

        $flushCodeFence = function () use (&$html, &$codeFenceLines, &$insideCodeFence): void {
            if (!$insideCodeFence && [] === $codeFenceLines) {
                return;
            }

            $code = htmlspecialchars(implode("\n", $codeFenceLines), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $html[] = sprintf('<pre class="article-code"><code>%s</code></pre>', $code);
            $codeFenceLines = [];
            $insideCodeFence = false;
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($insideCodeFence) {
                if ('```' === $trimmed) {
                    $flushCodeFence();
                    continue;
                }

                $codeFenceLines[] = rtrim($line, "\n");
                continue;
            }

            if (null !== $calloutType) {
                if (':::' === $trimmed) {
                    $flushCallout();
                    continue;
                }

                $calloutLines[] = $line;
                continue;
            }

            if ('' === $trimmed) {
                $flushParagraph();
                $flushList();
                $flushChecklist();
                $flushBlockquote();
                continue;
            }

            if ('```' === $trimmed) {
                $flushParagraph();
                $flushList();
                $flushChecklist();
                $flushBlockquote();
                $insideCodeFence = true;
                $codeFenceLines = [];
                continue;
            }

            if (preg_match('/^:::(info|warning|success)(?:\s+(.+))?$/', $trimmed, $matches) === 1) {
                $flushParagraph();
                $flushList();
                $flushChecklist();
                $flushBlockquote();
                $calloutType = $matches[1];
                $calloutTitle = $matches[2] ?? null;
                $calloutLines = [];
                continue;
            }

            if ('---' === $trimmed) {
                $flushParagraph();
                $flushList();
                $flushChecklist();
                $flushBlockquote();
                $html[] = '<hr>';
                continue;
            }

            if (preg_match('/^(#{2,3})\s+(.+)$/', $trimmed, $matches) === 1) {
                $flushParagraph();
                $flushList();
                $flushChecklist();
                $flushBlockquote();
                $level = strlen($matches[1]);
                $html[] = sprintf('<h%d>%s</h%d>', $level, $this->renderInlineArticleMarkup($matches[2]), $level);
                continue;
            }

            if (preg_match('/^!\[(.*?)\]\((https?:\/\/[^\s)]+)\)$/', $trimmed, $matches) === 1) {
                $flushParagraph();
                $flushList();
                $flushChecklist();
                $flushBlockquote();
                $alt = htmlspecialchars(trim($matches[1]), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
                $url = htmlspecialchars($matches[2], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
                $caption = '' !== trim($matches[1])
                    ? sprintf('<figcaption>%s</figcaption>', $this->renderInlineArticleMarkup($matches[1]))
                    : '';
                $html[] = sprintf(
                    '<figure class="article-media"><img src="%s" alt="%s" loading="lazy">%s</figure>',
                    $url,
                    $alt,
                    $caption,
                );
                continue;
            }

            if (preg_match('/^=>\s+(.+?)\s+\|\s+(https?:\/\/\S+)$/', $trimmed, $matches) === 1) {
                $flushParagraph();
                $flushList();
                $flushChecklist();
                $flushBlockquote();
                $label = $this->renderInlineArticleMarkup($matches[1]);
                $url = htmlspecialchars($matches[2], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
                $html[] = sprintf(
                    '<p><a class="article-cta" href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
                    $url,
                    $label,
                );
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $matches) === 1) {
                $flushParagraph();
                $flushList();
                $flushChecklist();
                $blockquote[] = $matches[1];
                continue;
            }

            if (preg_match('/^- (.+)$/', $trimmed, $matches) === 1) {
                if (preg_match('/^\[( |x|X)\]\s+(.+)$/', $matches[1], $checklistMatches) === 1) {
                    $flushParagraph();
                    $flushList();
                    $flushBlockquote();
                    $checklistItems[] = [
                        'checked' => \in_array($checklistMatches[1], ['x', 'X'], true),
                        'label' => $checklistMatches[2],
                    ];
                    continue;
                }

                $flushParagraph();
                $flushChecklist();
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
                $flushChecklist();
                $flushBlockquote();
                if ('ol' !== $listType) {
                    $flushList();
                    $listType = 'ol';
                }
                $listItems[] = $matches[1];
                continue;
            }

            $flushList();
            $flushChecklist();
            $flushBlockquote();
            $paragraph[] = $trimmed;
        }

        $flushCallout();
        $flushCodeFence();
        $flushParagraph();
        $flushList();
        $flushChecklist();
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
