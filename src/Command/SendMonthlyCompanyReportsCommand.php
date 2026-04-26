<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\Identity\Entity\Company;
use App\Module\Ticket\Service\CompanyMonthlyReportMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reports:send-monthly',
    description: 'Sends company-scoped monthly ticket reports by email.',
)]
final class SendMonthlyCompanyReportsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyMonthlyReportMailer $monthlyReportMailer,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Start date in Y-m-d. Defaults to first day of previous month.')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'End date in Y-m-d. Defaults to last day of previous month.')
            ->addOption('company-id', null, InputOption::VALUE_REQUIRED, 'Only send for one company id.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Send even if the company already has a report marked as sent for this period.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not send email, only log what would happen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            [$from, $to] = $this->resolvePeriod($input);
            $companyId = $this->resolveCompanyId($input);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->warning('Månadsrapportkommando stoppades av ogiltiga argument.', [
                'exception' => $exception,
            ]);
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $companyRepository = $this->entityManager->getRepository(Company::class);
        if (null !== $companyId) {
            $company = $companyRepository->find($companyId);
            $companies = $company instanceof Company ? [$company] : [];
        } else {
            /** @var list<Company> $companies */
            $companies = $companyRepository->findBy([
                'monthlyReportEnabled' => true,
                'isActive' => true,
            ], ['name' => 'ASC']);
        }
        $sent = 0;
        $skipped = 0;
        $force = (bool) $input->getOption('force');

        foreach ($companies as $company) {
            if (!$force && $this->wasAlreadySentForPeriod($company, $to)) {
                ++$skipped;

                continue;
            }

            if ($this->monthlyReportMailer->send($company, $from, $to, (bool) $input->getOption('dry-run'))) {
                ++$sent;
            } else {
                ++$skipped;
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf(
            'Månadsrapporter hanterade för %d företag. Skickade: %d. Hoppade över: %d.',
            \count($companies),
            $sent,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    private function wasAlreadySentForPeriod(Company $company, \DateTimeImmutable $periodEnd): bool
    {
        $lastSentAt = $company->getMonthlyReportLastSentAt();

        return $lastSentAt instanceof \DateTimeImmutable && $lastSentAt >= $periodEnd;
    }

    /**
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function resolvePeriod(InputInterface $input): array
    {
        $fromOption = trim((string) $input->getOption('from'));
        $toOption = trim((string) $input->getOption('to'));

        if ('' !== $fromOption || '' !== $toOption) {
            $from = $this->dateFromOption($fromOption, '--from');
            $to = $this->dateFromOption($toOption, '--to');

            if ($from > $to) {
                throw new \InvalidArgumentException('--from måste vara samma dag eller före --to.');
            }

            return [$from->setTime(0, 0), $to->setTime(23, 59, 59)];
        }

        $firstDayThisMonth = (new \DateTimeImmutable('first day of this month'))->setTime(0, 0);

        return [
            $firstDayThisMonth->modify('-1 month'),
            $firstDayThisMonth->modify('-1 second'),
        ];
    }

    private function resolveCompanyId(InputInterface $input): ?int
    {
        $companyId = trim((string) $input->getOption('company-id'));
        if ('' === $companyId) {
            return null;
        }

        if (!ctype_digit($companyId) || (int) $companyId < 1) {
            throw new \InvalidArgumentException('--company-id måste vara ett positivt heltal.');
        }

        return (int) $companyId;
    }

    private function dateFromOption(string $value, string $optionName): \DateTimeImmutable
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('Både --from och --to måste anges som Y-m-d.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date instanceof \DateTimeImmutable || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new \InvalidArgumentException(sprintf('%s måste anges som ett giltigt datum i formatet Y-m-d.', $optionName));
        }

        return $date;
    }
}
