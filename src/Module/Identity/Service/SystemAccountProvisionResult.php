<?php

declare(strict_types=1);

namespace App\Module\Identity\Service;

final class SystemAccountProvisionResult
{
    /**
     * @param list<string> $createdEmails
     * @param list<string> $updatedEmails
     */
    public function __construct(
        private readonly array $createdEmails,
        private readonly array $updatedEmails,
    ) {
    }

    /**
     * @return list<string>
     */
    public function createdEmails(): array
    {
        return $this->createdEmails;
    }

    /**
     * @return list<string>
     */
    public function updatedEmails(): array
    {
        return $this->updatedEmails;
    }

    public function changed(): bool
    {
        return [] !== $this->createdEmails || [] !== $this->updatedEmails;
    }
}
