<?php

declare(strict_types=1);

namespace App\Module\PublicPortal\Controller;

use App\Module\PublicPortal\Service\PublicSiteSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SearchController extends AbstractController
{
    public function __construct(
        private readonly PublicSiteSearch $publicSiteSearch,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/sok', name: 'app_public_search', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $searchQuery = trim($request->query->getString('q'));
        $selectedFilter = trim($request->query->getString('filter', 'all'));
        $allResults = '' !== $searchQuery ? $this->publicSiteSearch->search($searchQuery) : [];

        $filterOptions = [
            ['value' => 'all', 'label' => $this->translator->trans('search.filter.all')],
            ['value' => 'page', 'label' => $this->translator->trans('search.filter.page')],
            ['value' => 'news', 'label' => $this->translator->trans('search.filter.news')],
            ['value' => 'knowledge_base', 'label' => $this->translator->trans('search.filter.knowledge_base')],
            ['value' => 'system_status', 'label' => $this->translator->trans('search.filter.system_status')],
            ['value' => 'support', 'label' => $this->translator->trans('search.filter.support')],
            ['value' => 'contact', 'label' => $this->translator->trans('search.filter.contact')],
            ['value' => 'sign_in', 'label' => $this->translator->trans('search.filter.sign_in')],
            ['value' => 'account', 'label' => $this->translator->trans('search.filter.account')],
        ];

        $validFilters = array_map(
            static fn (array $option): string => $option['value'],
            $filterOptions,
        );
        if (!\in_array($selectedFilter, $validFilters, true)) {
            $selectedFilter = 'all';
        }

        $filterCounts = ['all' => count($allResults)];
        foreach ($filterOptions as $option) {
            if ('all' === $option['value']) {
                continue;
            }

            $filterCounts[$option['value']] = count(array_filter(
                $allResults,
                static fn (array $result): bool => $result['section'] === $option['value'],
            ));
        }

        $searchResults = 'all' === $selectedFilter
            ? $allResults
            : array_values(array_filter(
                $allResults,
                static fn (array $result): bool => $result['section'] === $selectedFilter,
            ));

        $selectedFilterLabel = $this->translator->trans('search.filter.all');
        foreach ($filterOptions as $option) {
            if ($option['value'] === $selectedFilter) {
                $selectedFilterLabel = $option['label'];
                break;
            }
        }

        return $this->render('public/search.html.twig', [
            'searchQuery' => $searchQuery,
            'searchResults' => $searchResults,
            'allResultsCount' => count($allResults),
            'selectedFilter' => $selectedFilter,
            'selectedFilterLabel' => $selectedFilterLabel,
            'filterOptions' => $filterOptions,
            'filterCounts' => $filterCounts,
        ]);
    }
}
