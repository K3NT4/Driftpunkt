<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\Identity\Service\SystemAccountProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:system-accounts:ensure',
    description: 'Creates or updates the reserved super admin and standard admin accounts.',
)]
final class EnsureSystemAccountsCommand extends Command
{
    public function __construct(
        private readonly SystemAccountProvisioner $systemAccountProvisioner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->systemAccountProvisioner->ensureRequiredAdminAccounts();

        if (!$result->changed()) {
            $io->success('Obligatoriska admin-konton finns redan.');

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Admin-konton skapade/uppdaterade: %s',
            implode(', ', array_merge($result->createdEmails(), $result->updatedEmails())),
        ));

        return Command::SUCCESS;
    }
}
