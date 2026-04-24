<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Identity\Service\SystemAccountProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Creates the first admin or super admin account for a new installation.',
)]
final class CreateInitialAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address')
            ->addArgument('password', InputArgument::REQUIRED, 'Plain text password')
            ->addArgument('first-name', InputArgument::OPTIONAL, 'First name', 'System')
            ->addArgument('last-name', InputArgument::OPTIONAL, 'Last name', 'Admin')
            ->addArgument('type', InputArgument::OPTIONAL, 'admin or super_admin', UserType::SUPER_ADMIN->value);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $password = (string) $input->getArgument('password');
        $firstName = (string) $input->getArgument('first-name');
        $lastName = (string) $input->getArgument('last-name');
        $type = UserType::tryFrom((string) $input->getArgument('type'));

        if (!$type instanceof UserType || !\in_array($type, [UserType::ADMIN, UserType::SUPER_ADMIN], true)) {
            $io->error('The initial account type must be admin or super_admin.');

            return Command::INVALID;
        }

        if (SystemAccountProvisioner::RESERVED_SUPER_ADMIN_EMAIL === $email) {
            $io->error('That email address is reserved for a protected system account and cannot be created manually.');

            return Command::FAILURE;
        }

        $existingUser = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if (null !== $existingUser) {
            $io->error(sprintf('A user with email "%s" already exists.', $email));

            return Command::FAILURE;
        }

        $user = new User($email, $firstName, $lastName, $type);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Created %s account for %s. Log in and activate MFA from the admin security warning as soon as possible.',
            $type->label(),
            $user->getEmail(),
        ));

        return Command::SUCCESS;
    }
}
