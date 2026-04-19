<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\Maintenance\Service\MaintenanceMode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:maintenance',
    description: 'Enable, disable, or inspect maintenance mode.',
)]
final class MaintenanceModeCommand extends Command
{
    public function __construct(
        private readonly MaintenanceMode $maintenanceMode,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::OPTIONAL, 'on, off, or status', 'status')
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Optional message for maintenance mode')
            ->addOption('start', null, InputOption::VALUE_REQUIRED, 'Optional schedule start in a format accepted by DateTimeImmutable')
            ->addOption('end', null, InputOption::VALUE_REQUIRED, 'Optional schedule end in a format accepted by DateTimeImmutable');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = mb_strtolower((string) $input->getArgument('action'));

        return match ($action) {
            'on' => $this->enable($io, $input->getOption('message'), $input->getOption('start'), $input->getOption('end')),
            'schedule' => $this->schedule($io, $input->getOption('message'), $input->getOption('start'), $input->getOption('end')),
            'off' => $this->disable($io),
            'status' => $this->status($io),
            default => Command::INVALID,
        };
    }

    private function enable(SymfonyStyle $io, mixed $message, mixed $start, mixed $end): int
    {
        $this->maintenanceMode->updateSettings(
            true,
            \is_string($message) ? $message : null,
            $this->parseOptionalDateTime($start),
            $this->parseOptionalDateTime($end),
        );
        $io->success('Maintenance mode enabled.');

        return Command::SUCCESS;
    }

    private function schedule(SymfonyStyle $io, mixed $message, mixed $start, mixed $end): int
    {
        $startAt = $this->parseOptionalDateTime($start);
        $endAt = $this->parseOptionalDateTime($end);

        if (!$startAt instanceof \DateTimeImmutable) {
            $io->error('A valid --start value is required when scheduling maintenance.');

            return Command::INVALID;
        }

        if ($endAt instanceof \DateTimeImmutable && $endAt < $startAt) {
            $io->error('The --end time cannot be earlier than --start.');

            return Command::INVALID;
        }

        $state = $this->maintenanceMode->getState();
        $this->maintenanceMode->updateSettings(
            false,
            \is_string($message) ? $message : ($state['message'] ?? null),
            $startAt,
            $endAt,
        );
        $io->success('Maintenance has been scheduled.');

        return Command::SUCCESS;
    }

    private function disable(SymfonyStyle $io): int
    {
        $this->maintenanceMode->disable();
        $io->success('Maintenance mode disabled.');

        return Command::SUCCESS;
    }

    private function status(SymfonyStyle $io): int
    {
        $state = $this->maintenanceMode->getState();
        $io->definitionList(
            ['Manual Enabled' => $state['enabled'] ? 'yes' : 'no'],
            ['Effective Enabled' => $state['effectiveEnabled'] ? 'yes' : 'no'],
            ['Mode' => $state['mode']],
            ['Message' => $state['message'] ?? 'none'],
            ['Scheduled Start' => $state['scheduledStartAt'] ?? 'n/a'],
            ['Scheduled End' => $state['scheduledEndAt'] ?? 'n/a'],
            ['Updated At' => $state['updatedAt'] ?? 'n/a'],
        );

        return Command::SUCCESS;
    }

    private function parseOptionalDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
