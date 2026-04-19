<?php

declare(strict_types=1);

namespace App\Module\Identity\Service;

use App\Module\Identity\Entity\PasswordResetRequest;
use App\Module\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function createResetRequest(User $user): string
    {
        $this->expireActiveRequestsForUser($user);

        $token = bin2hex(random_bytes(32));
        $request = new PasswordResetRequest(
            $user,
            $this->hashToken($token),
            new \DateTimeImmutable('+60 minutes'),
        );

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $token;
    }

    public function findActiveRequestByToken(string $token): ?PasswordResetRequest
    {
        $request = $this->entityManager->getRepository(PasswordResetRequest::class)->findOneBy([
            'tokenHash' => $this->hashToken($token),
        ]);

        if (!$request instanceof PasswordResetRequest || !$request->isActive()) {
            return null;
        }

        return $request;
    }

    public function resetPassword(PasswordResetRequest $resetRequest, string $plainPassword): void
    {
        $userId = $resetRequest->getUser()->getId();
        $user = null !== $userId ? $this->entityManager->getRepository(User::class)->find($userId) : null;

        if (!$user instanceof User) {
            throw new \RuntimeException('Kunde inte hitta användaren för lösenordsåterställningen.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $resetRequest->markAsUsed();
        $this->expireActiveRequestsForUser($user, $resetRequest);

        $this->entityManager->flush();
    }

    private function expireActiveRequestsForUser(User $user, ?PasswordResetRequest $exclude = null): void
    {
        $requests = $this->entityManager->getRepository(PasswordResetRequest::class)->findBy([
            'user' => $user,
            'usedAt' => null,
        ]);

        $now = new \DateTimeImmutable();

        foreach ($requests as $request) {
            if (!$request instanceof PasswordResetRequest) {
                continue;
            }

            if (null !== $exclude && $request->getId() === $exclude->getId()) {
                continue;
            }

            if ($request->isActive($now)) {
                $request->markAsUsed($now);
            }
        }
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
