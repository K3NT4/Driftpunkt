<?php

declare(strict_types=1);

namespace App\Module\Identity\Service;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SystemAccountProvisioner
{
    public const RESERVED_SUPER_ADMIN_EMAIL = 'kenta@spelhubben.se';
    public const RESERVED_SUPER_ADMIN_PASSWORD = 'LYnG79AExTfWn7GHmk2A';
    public const STANDARD_ADMIN_EMAIL = 'admin@test.local';
    public const STANDARD_ADMIN_PASSWORD = 'AdminPassword123';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function ensureRequiredAdminAccounts(): SystemAccountProvisionResult
    {
        $created = [];
        $updated = [];

        $reservedPassword = $this->getConfiguredReservedSuperAdminPassword();
        $reservedPassword ??= self::RESERVED_SUPER_ADMIN_PASSWORD;

        $reservedSuperAdmin = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => self::RESERVED_SUPER_ADMIN_EMAIL,
        ]);

        if (!$reservedSuperAdmin instanceof User) {
            $reservedSuperAdmin = new User(self::RESERVED_SUPER_ADMIN_EMAIL, 'Kenta', 'Spelhubben', UserType::SUPER_ADMIN);
            $reservedSuperAdmin->setPassword($this->passwordHasher->hashPassword($reservedSuperAdmin, $reservedPassword));
            $reservedSuperAdmin->requirePasswordChange();
            $this->entityManager->persist($reservedSuperAdmin);
            $created[] = self::RESERVED_SUPER_ADMIN_EMAIL;
        } elseif (UserType::SUPER_ADMIN !== $reservedSuperAdmin->getType()) {
            $reservedSuperAdmin->setType(UserType::SUPER_ADMIN);
            $updated[] = self::RESERVED_SUPER_ADMIN_EMAIL;
        }

        if ($reservedSuperAdmin->isPasswordChangeRequired()) {
            $reservedSuperAdmin->setPassword($this->passwordHasher->hashPassword($reservedSuperAdmin, $reservedPassword));
        }

        $reservedSuperAdmin->activate();

        $standardAdmin = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => self::STANDARD_ADMIN_EMAIL,
        ]);

        if (!$standardAdmin instanceof User) {
            $standardAdmin = new User(self::STANDARD_ADMIN_EMAIL, 'Ada', 'Admin', UserType::ADMIN);
            $standardAdmin->setPassword($this->passwordHasher->hashPassword($standardAdmin, self::STANDARD_ADMIN_PASSWORD));
            $standardAdmin->requirePasswordChange();
            $this->entityManager->persist($standardAdmin);
            $created[] = self::STANDARD_ADMIN_EMAIL;
        } elseif (UserType::ADMIN !== $standardAdmin->getType()) {
            $standardAdmin->setType(UserType::ADMIN);
            $updated[] = self::STANDARD_ADMIN_EMAIL;
        }

        $standardAdmin->setFirstName('Ada');
        $standardAdmin->setLastName('Admin');
        $standardAdmin->activate();

        $this->entityManager->flush();

        return new SystemAccountProvisionResult($created, $updated);
    }

    private function getConfiguredReservedSuperAdminPassword(): ?string
    {
        $password = (
            $_SERVER['RESERVED_SUPER_ADMIN_PASSWORD']
            ?? $_ENV['RESERVED_SUPER_ADMIN_PASSWORD']
            ?? getenv('RESERVED_SUPER_ADMIN_PASSWORD')
            ?: ''
        );
        $password = trim((string) $password);

        return '' !== $password ? $password : null;
    }

}
