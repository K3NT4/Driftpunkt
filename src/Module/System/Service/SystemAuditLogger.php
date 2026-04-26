<?php

declare(strict_types=1);

namespace App\Module\System\Service;

use App\Module\Identity\Entity\User;
use App\Module\System\Entity\SystemAuditLog;
use Doctrine\ORM\EntityManagerInterface;

final class SystemAuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function log(?User $actor, string $action, string $title, string $message): void
    {
        $log = new SystemAuditLog($actor, $action, $title, $message);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /**
     * @return list<SystemAuditLog>
     */
    public function listRecent(int $limit = 6): array
    {
        return $this->entityManager
            ->getRepository(SystemAuditLog::class)
            ->createQueryBuilder('log')
            ->orderBy('log.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }
}
