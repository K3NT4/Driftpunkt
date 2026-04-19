<?php

declare(strict_types=1);

namespace App\Module\PublicPortal\Controller;

use App\Module\PublicPortal\Service\PublicSiteSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    public function __construct(
        private readonly PublicSiteSearch $publicSiteSearch,
    ) {
    }

    #[Route('/sok', name: 'app_public_search', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $searchQuery = trim($request->query->getString('q'));
        $selectedFilter = trim($request->query->getString('filter', 'alla'));
        $allResults = '' !== $searchQuery ? $this->publicSiteSearch->search($searchQuery) : [];

        $filterOptions = [
            ['value' => 'alla', 'label' => 'Alla'],
            ['value' => 'Sida', 'label' => 'Sidor'],
            ['value' => 'Nyhet', 'label' => 'Nyheter'],
            ['value' => 'Kunskapsbas', 'label' => 'Kunskapsbas'],
            ['value' => 'Systemstatus', 'label' => 'Systemstatus'],
            ['value' => 'Support', 'label' => 'Support'],
            ['value' => 'Kontakt', 'label' => 'Kontakt'],
            ['value' => 'Inloggning', 'label' => 'Inloggning'],
            ['value' => 'Konto', 'label' => 'Konto'],
        ];

        $validFilters = array_map(
            static fn (array $option): string => $option['value'],
            $filterOptions,
        );
        if (!\in_array($selectedFilter, $validFilters, true)) {
            $selectedFilter = 'alla';
        }

        $filterCounts = ['alla' => count($allResults)];
        foreach ($filterOptions as $option) {
            if ('alla' === $option['value']) {
                continue;
            }

            $filterCounts[$option['value']] = count(array_filter(
                $allResults,
                static fn (array $result): bool => $result['section'] === $option['value'],
            ));
        }

        $searchResults = 'alla' === $selectedFilter
            ? $allResults
            : array_values(array_filter(
                $allResults,
                static fn (array $result): bool => $result['section'] === $selectedFilter,
            ));

        $selectedFilterLabel = 'Alla';
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
