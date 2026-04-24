<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Identity\Service\SystemAccountProvisioner;
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
            'email' => 'tech@test.local',
            'password' => 'TechPassword123',
            'firstName' => 'Ture',
            'lastName' => 'Tekniker',
            'type' => UserType::TECHNICIAN,
            'mfaEnabled' => false,
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
        private readonly SystemAccountProvisioner $systemAccountProvisioner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $provisionResult = $this->systemAccountProvisioner->ensureRequiredAdminAccounts();

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
            $user->requirePasswordChange();

            if ($account['mfaEnabled']) {
                $user->enableMfa();
            } else {
                $user->disableMfa();
            }

            $user->activate();
        }

        $this->entityManager->flush();

        $io->success('Testkonton skapade/uppdaterade.');
        if ($provisionResult->changed()) {
            $io->text(sprintf(
                'Obligatoriska admin-konton skapade/uppdaterade: %s',
                implode(', ', array_merge($provisionResult->createdEmails(), $provisionResult->updatedEmails())),
            ));
        }

        $io->table(
            ['Roll', 'E-post', 'Losenord'],
            [
                ['Reserv super admin', SystemAccountProvisioner::RESERVED_SUPER_ADMIN_EMAIL, 'Dolt, byte kravs vid forsta inloggning'],
                ['Admin', SystemAccountProvisioner::STANDARD_ADMIN_EMAIL, SystemAccountProvisioner::STANDARD_ADMIN_PASSWORD],
                ['Tekniker', 'tech@test.local', 'TechPassword123'],
                ['Kund', 'customer@test.local', 'CustomerPassword123'],
            ],
        );

        return Command::SUCCESS;
    }
}
