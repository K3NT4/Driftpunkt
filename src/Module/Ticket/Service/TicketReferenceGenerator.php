<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Identity\Entity\Company;
use App\Module\Ticket\Entity\Ticket;
use Doctrine\ORM\EntityManagerInterface;

final class TicketReferenceGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function nextReference(?Company $company = null): string
    {
        return $this->nextReferences(1, $company)[0];
    }

    /**
     * @return list<string>
     */
    public function nextReferences(int $count, ?Company $company = null): array
    {
        $count = max(1, $count);

        if (!$company instanceof Company || !$company->usesCustomTicketSequence()) {
            return $this->nextDefaultReferences($count);
        }

        $prefix = $this->normalizePrefix($company->getTicketReferencePrefix() ?? '');
        if ('' === $prefix) {
            $prefix = $this->suggestPrefixFromCompanyName($company->getName());
            $company->setTicketReferencePrefix($prefix);
        }

        $references = [];
        for ($index = 0; $index < $count; ++$index) {
            $references[] = sprintf('%s-%04d', $prefix, $company->reserveNextTicketNumber());
        }

        return $references;
    }

    public function ticketReferencePattern(): string
    {
        return '[A-Z0-9]{2,16}-\d{4,}';
    }

    public function ticketReferenceRegex(): string
    {
        return '/\b('.$this->ticketReferencePattern().')\b/i';
    }

    public function isValidReference(string $reference): bool
    {
        return 1 === preg_match('/^'.$this->ticketReferencePattern().'$/', strtoupper(trim($reference)));
    }

    public function normalizePrefix(string $prefix): string
    {
        $normalized = strtoupper(trim($prefix));
        $normalized = preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? '';

        return mb_substr($normalized, 0, 16);
    }

    public function suggestPrefixFromCompanyName(string $companyName): string
    {
        $normalized = strtoupper(trim($companyName));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;
        $normalized = preg_replace('/[^A-Z0-9 ]+/', ' ', $normalized) ?? '';
        $parts = array_values(array_filter(
            preg_split('/\s+/', $normalized) ?: [],
            static fn (string $part): bool => '' !== $part && !\in_array($part, ['AB', 'AKTIEBOLAG', 'HB', 'KB', 'EK', 'LTD', 'INC', 'LLC'], true),
        ));

        $prefix = '';
        foreach ($parts as $part) {
            $prefix .= mb_substr($part, 0, 3);
            if (mb_strlen($prefix) >= 6) {
                break;
            }
        }

        $prefix = $this->normalizePrefix($prefix);
        if (mb_strlen($prefix) >= 3) {
            return $prefix;
        }

        $fallback = $this->normalizePrefix(str_replace(' ', '', $normalized));
        if (mb_strlen($fallback) >= 3) {
            return mb_substr($fallback, 0, 6);
        }

        return 'KUND';
    }

    /**
     * @return list<string>
     */
    private function nextDefaultReferences(int $count): array
    {
        $references = [];
        $existingCount = (int) $this->entityManager->getRepository(Ticket::class)->count([]);

        for ($index = 0; $index < $count; ++$index) {
            $references[] = sprintf('DP-%04d', $existingCount + 1001 + $index);
        }

        return $references;
    }
}
