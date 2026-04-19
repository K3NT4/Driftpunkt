<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-test-accounts',
    description: 'Creates or updates local test accounts for admin, technician and customer.',
)]
final class CreateTestAccountsCommand extends Command
{
    /**
     * @var array<int, array{email: string, password: string, firstName: string, lastName: string, type: UserType, mfaEnabled: bool}>
     */
    private const TEST_ACCOUNTS = [
        [
            'email' => 'admin@test.local',
            'password' => 'AdminPassword123',
            'firstName' => 'Ada',
            'lastName' => 'Admin',
            'type' => UserType::SUPER_ADMIN,
            'mfaEnabled' => true,
        ],
        [
            'email' => 'tech@test.local',
            'password' => 'TechPassword123',
            'firstName' => 'Ture',
            'lastName' => 'Tekniker',
            'type' => UserType::TECHNICIAN,
            'mfaEnabled' => true,
        ],
        [
            'email' => 'customer@test.local',
            'password' => 'CustomerPassword123',
            'firstName' => 'Klara',
            'lastName' => 'Kund',
            'type' => UserType::CUSTOMER,
            'mfaEnabled' => false,
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach (self::TEST_ACCOUNTS as $account) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => mb_strtolower($account['email']),
            ]);

            if (!$user instanceof User) {
                $user = new User(
                    $account['email'],
                    $account['firstName'],
                    $account['lastName'],
                    $account['type'],
                );
                $this->entityManager->persist($user);
            }

            $user->setFirstName($account['firstName']);
            $user->setLastName($account['lastName']);
            $user->setType($account['type']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $account['password']));

            if ($account['mfaEnabled']) {
                $user->enableMfa();
            } else {
                $user->disableMfa();
            }

            $user->activate();
        }

        $this->entityManager->flush();

        $io->success('Testkonton skapade/uppdaterade.');
        $io->table(
            ['Roll', 'E-post', 'Losenord'],
            [
                ['Super admin', 'admin@test.local', 'AdminPassword123'],
                ['Tekniker', 'tech@test.local', 'TechPassword123'],
                ['Kund', 'customer@test.local', 'CustomerPassword123'],
            ],
        );

        return Command::SUCCESS;
    }
}
