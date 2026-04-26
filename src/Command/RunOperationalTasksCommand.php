<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\System\Service\OperationalTaskRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:operations:run-due',
    description: 'Runs due Driftpunkt operational tasks from the app-managed scheduler fallback.',
)]
final class RunOperationalTasksCommand extends Command
{
    public function __construct(
        private readonly OperationalTaskRunner $operationalTaskRunner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $results = $this->operationalTaskRunner->runDueTasks();

        if ([] === $results) {
            $io->success('Inga interna driftjobb var förfallna.');

            return Command::SUCCESS;
        }

        $failed = 0;
        foreach ($results as $result) {
            if ($result['succeeded']) {
                $io->writeln(sprintf('[OK] %s kördes klart.', $result['label']));

                continue;
            }

            ++$failed;
            $io->warning(sprintf('%s misslyckades: %s', $result['label'], $result['output']));
        }

        if ($failed > 0) {
            $io->error(sprintf('%d interna driftjobb misslyckades.', $failed));

            return Command::FAILURE;
        }

        $io->success(sprintf('%d interna driftjobb kördes klart.', \count($results)));

        return Command::SUCCESS;
    }
}
