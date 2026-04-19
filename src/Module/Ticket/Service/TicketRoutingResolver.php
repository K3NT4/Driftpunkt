<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Identity\Entity\User;
use App\Module\Ticket\Entity\TicketCategory;
use App\Module\Ticket\Entity\TicketIntakeTemplate;
use App\Module\Ticket\Entity\TicketRoutingRule;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketRequestType;
use Doctrine\ORM\EntityManagerInterface;

final class TicketRoutingResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, string> $intakeAnswers
     */
    public function resolveRule(?TicketCategory $category, ?User $requester, TicketRequestType $requestType, TicketImpactLevel $impactLevel, array $intakeAnswers = [], ?TicketIntakeTemplate $intakeTemplate = null): ?TicketRoutingRule
    {
        $rules = $this->entityManager->getRepository(TicketRoutingRule::class)->findBy(
            ['isActive' => true],
            ['sortOrder' => 'ASC', 'id' => 'ASC'],
        );

        $requesterType = $requester?->getType();
        $bestRule = null;
        $bestScore = -1;

        foreach ($rules as $rule) {
            $score = 0;

            if ($rule->getCategory() instanceof TicketCategory) {
                if (!$category instanceof TicketCategory || $rule->getCategory()->getId() !== $category->getId()) {
                    continue;
                }

                $score += 2;
            }

            if (null !== $rule->getCustomerType()) {
                if (null === $requesterType || $rule->getCustomerType() !== $requesterType) {
                    continue;
                }

                $score += 1;
            }

            if (null !== $rule->getRequestType()) {
                if ($rule->getRequestType() !== $requestType) {
                    continue;
                }

                $score += 2;
            }

            if (null !== $rule->getImpactLevel()) {
                if ($rule->getImpactLevel() !== $impactLevel) {
                    continue;
                }

                $score += 1;
            }

            if (null !== $rule->getIntakeTemplateFamily()) {
                if (
                    !$intakeTemplate instanceof TicketIntakeTemplate
                    || $rule->getIntakeTemplateFamily() !== $intakeTemplate->getVersionFamily()
                ) {
                    continue;
                }

                $score += 2;
            }

            if (null !== $rule->getIntakeFieldKey()) {
                $fieldValue = $intakeAnswers[$rule->getIntakeFieldKey()] ?? null;
                if (null === $fieldValue) {
                    continue;
                }

                if (null !== $rule->getIntakeFieldValue()) {
                    if (mb_strtolower(trim($fieldValue)) !== mb_strtolower($rule->getIntakeFieldValue())) {
                        continue;
                    }

                    $score += 2;
                } else {
                    $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestRule = $rule;
                $bestScore = $score;
            }
        }

        return $bestRule;
    }
}
