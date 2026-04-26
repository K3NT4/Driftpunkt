<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\System\Service\CodeUpdateApplyRunner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:code-update:apply-run',
    description: 'Processes a queued code update apply run in the background.',
)]
final class RunCodeUpdateApplyCommand extends Command
{
    public function __construct(
        private readonly CodeUpdateApplyRunner $codeUpdateApplyRunner,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('run-id', InputArgument::REQUIRED, 'Queued code update run id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $runId = (string) $input->getArgument('run-id');
            $run = $this->codeUpdateApplyRunner->processQueuedRun($runId);
        } catch (\Throwable $exception) {
            $this->logger->error('Koduppdateringskörning misslyckades oväntat.', [
                'run_id' => $runId ?? (string) $input->getArgument('run-id'),
                'exception' => $exception,
            ]);
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ('failed' === $run['status']) {
            $io->error($run['output']);

            return Command::FAILURE;
        }

        $io->success($run['output']);

        return Command::SUCCESS;
    }
}
