<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\Identity\Service\SystemAccountProvisioner;
use App\Module\Ticket\Entity\SlaPolicy;
use App\Module\Ticket\Entity\TicketCategory;
use App\Module\News\Entity\NewsArticle;
use App\Module\News\Enum\NewsCategory;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketPriority;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:install:fresh',
    description: 'Initializes a fresh installation by creating the schema and baselining migrations for the current database platform.',
)]
final class InstallFreshCommand extends Command
{
    /**
     * @var array<int, array{name: string, description: string}>
     */
    private const DEFAULT_TICKET_CATEGORIES = [
        [
            'name' => 'Allmän support',
            'description' => 'Standardkategori för inkommande supportärenden som ännu inte matchar en mer specifik kö.',
        ],
        [
            'name' => 'Nätverk',
            'description' => 'Incidenter och beställningar för uppkoppling, VPN, Wi-Fi och brandvägg.',
        ],
        [
            'name' => 'Arbetsplats',
            'description' => 'Datorer, klientmiljö, skrivare och användarnära arbetsplatsstöd.',
        ],
        [
            'name' => 'E-post',
            'description' => 'Mailboxar, klienter, leveransproblem och e-postflöden.',
        ],
        [
            'name' => 'Behörighet',
            'description' => 'Konton, lösenord, åtkomst och behörighetsändringar.',
        ],
        [
            'name' => 'Server & drift',
            'description' => 'Servermiljöer, databaser, lagring, övervakning och andra driftrelaterade ärenden.',
        ],
        [
            'name' => 'Applikationer',
            'description' => 'Fel, frågor och beställningar kopplade till verksamhetssystem och programvaror.',
        ],
        [
            'name' => 'Hårdvara',
            'description' => 'Fysisk utrustning som datorer, skärmar, dockor, telefoner och kringutrustning.',
        ],
        [
            'name' => 'Beställning',
            'description' => 'Nya tjänster, användare, utrustning eller förändringar som ska planeras och levereras.',
        ],
    ];

    /**
     * @var array<int, array{name: string, description: string, firstResponseHours: int, resolutionHours: int, firstResponseWarningHours: int, resolutionWarningHours: int, priority: TicketPriority, escalationLevel: TicketEscalationLevel}>
     */
    private const DEFAULT_SLA_POLICIES = [
        [
            'name' => 'Standard 8/24',
            'description' => 'Standard-SLA för normala supportärenden.',
            'firstResponseHours' => 8,
            'resolutionHours' => 24,
            'firstResponseWarningHours' => 6,
            'resolutionWarningHours' => 20,
            'priority' => TicketPriority::NORMAL,
            'escalationLevel' => TicketEscalationLevel::NONE,
        ],
        [
            'name' => 'Prioriterad 2/8',
            'description' => 'Snabbare SLA för prioriterade eller verksamhetskritiska ärenden.',
            'firstResponseHours' => 2,
            'resolutionHours' => 8,
            'firstResponseWarningHours' => 1,
            'resolutionWarningHours' => 6,
            'priority' => TicketPriority::HIGH,
            'escalationLevel' => TicketEscalationLevel::LEAD,
        ],
    ];

    private const DEFAULT_NEWS_ARTICLE = [
        'title' => 'Välkommen till Driftpunkt',
        'summary' => 'Installationen är klar och nyhetsflödet är redo för driftinformation, uppdateringar och viktiga meddelanden.',
        'body' => <<<'TEXT'
Driftpunkt är nu installerat och redo att användas.

Här kan ni publicera nyheter, releaseinformation och driftmeddelanden som visas på startsidan, i nyhetsflödet och där kunder eller tekniker behöver snabb överblick.

Ett bra nästa steg är att uppdatera den här första nyheten med er egen introduktion, kontaktvägar och information om hur ni vill kommunicera planerade underhåll.
TEXT,
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly SystemAccountProvisioner $systemAccountProvisioner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'skip-test-accounts',
            null,
            InputOption::VALUE_NONE,
            'Skip creating the standard technician and customer test accounts during fresh install.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $databaseName = $this->connection->getDatabase();
        if (null === $databaseName || '' === trim($databaseName)) {
            $io->error('Ingen databas är konfigurerad i den aktiva anslutningen.');

            return Command::FAILURE;
        }

        $io->title('Fresh install');
        $io->text(sprintf('Anvander databas: %s', $databaseName));

        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            if (!$this->runConsoleCommand('doctrine:database:create', [
                '--if-not-exists' => true,
            ], $output, $io)) {
                return Command::FAILURE;
            }
        }

        $this->connection->close();

        $existingTables = $this->connection->createSchemaManager()->listTableNames();
        if ([] !== $existingTables) {
            $io->error(sprintf(
                'Databasen ar inte tom. Fresh install stoppar for att skydda befintliga tabeller: %s',
                implode(', ', $existingTables),
            ));

            return Command::FAILURE;
        }

        if (!$this->runConsoleCommand('doctrine:schema:create', [], $output, $io)) {
            return Command::FAILURE;
        }

        if (!$this->runConsoleCommand('doctrine:migrations:sync-metadata-storage', [], $output, $io)) {
            return Command::FAILURE;
        }

        if (!$this->runConsoleCommand('doctrine:migrations:version', [
            '--add' => true,
            '--all' => true,
            '--no-interaction' => true,
        ], $output, $io)) {
            return Command::FAILURE;
        }

        $this->seedDefaultTicketData($io);
        $this->seedDefaultNewsArticle($io);
        $this->ensureRequiredAdminAccounts($io);

        if (!$input->getOption('skip-test-accounts')) {
            if (!$this->runConsoleCommand('app:create-test-accounts', [], $output, $io)) {
                return Command::FAILURE;
            }
        }

        $io->success(
            $input->getOption('skip-test-accounts')
                ? 'Fresh install slutford. Obligatoriska admin-konton finns pa plats.'
                : 'Fresh install slutford. Obligatoriska admin-konton samt standardkonton for tekniker och kund skapades ocksa.',
        );

        return Command::SUCCESS;
    }

    /**
     * @param array<string, bool|string> $arguments
     */
    private function runConsoleCommand(string $name, array $arguments, OutputInterface $output, SymfonyStyle $io): bool
    {
        $application = $this->getApplication();
        if (null === $application) {
            $io->error('Kunde inte hamta Symfony Console-application.');

            return false;
        }

        $io->section(sprintf('Kor %s', $name));

        $command = $application->find($name);
        $commandInput = new ArrayInput(array_merge([
            'command' => $name,
        ], $arguments));
        $commandInput->setInteractive(false);

        $exitCode = $command->run($commandInput, $output);
        if (Command::SUCCESS !== $exitCode) {
            $io->error(sprintf('%s misslyckades med exit code %d.', $name, $exitCode));

            return false;
        }

        return true;
    }

    private function seedDefaultTicketData(SymfonyStyle $io): void
    {
        $io->section('Skapar standardkategorier och standard-SLA');

        foreach (self::DEFAULT_TICKET_CATEGORIES as $defaultCategory) {
            $category = $this->entityManager->getRepository(TicketCategory::class)->findOneBy([
                'name' => $defaultCategory['name'],
            ]);

            if (!$category instanceof TicketCategory) {
                $category = new TicketCategory($defaultCategory['name']);
                $this->entityManager->persist($category);
            }

            $category->setDescription($defaultCategory['description']);
            $category->activate();
        }

        foreach (self::DEFAULT_SLA_POLICIES as $defaultPolicy) {
            $slaPolicy = $this->entityManager->getRepository(SlaPolicy::class)->findOneBy([
                'name' => $defaultPolicy['name'],
            ]);

            if (!$slaPolicy instanceof SlaPolicy) {
                $slaPolicy = new SlaPolicy(
                    $defaultPolicy['name'],
                    $defaultPolicy['firstResponseHours'],
                    $defaultPolicy['resolutionHours'],
                );
                $this->entityManager->persist($slaPolicy);
            }

            $slaPolicy
                ->setDescription($defaultPolicy['description'])
                ->setFirstResponseHours($defaultPolicy['firstResponseHours'])
                ->setResolutionHours($defaultPolicy['resolutionHours'])
                ->setFirstResponseWarningHours($defaultPolicy['firstResponseWarningHours'])
                ->setResolutionWarningHours($defaultPolicy['resolutionWarningHours'])
                ->setDefaultPriorityEnabled(true)
                ->setDefaultPriority($defaultPolicy['priority'])
                ->setDefaultEscalationEnabled(true)
                ->setDefaultEscalationLevel($defaultPolicy['escalationLevel'])
                ->setDefaultAssigneeEnabled(false)
                ->setDefaultAssignee(null)
                ->setDefaultTeamEnabled(false)
                ->setDefaultTeam(null)
                ->activate();
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Standarddata skapad/uppdaterad: %d kategorier och %d SLA-policyer.',
            count(self::DEFAULT_TICKET_CATEGORIES),
            count(self::DEFAULT_SLA_POLICIES),
        ));
    }

    private function seedDefaultNewsArticle(SymfonyStyle $io): void
    {
        $io->section('Skapar första nyheten');

        $article = $this->entityManager->getRepository(NewsArticle::class)->findOneBy([
            'title' => self::DEFAULT_NEWS_ARTICLE['title'],
        ]);

        if (!$article instanceof NewsArticle) {
            $article = new NewsArticle(
                self::DEFAULT_NEWS_ARTICLE['title'],
                self::DEFAULT_NEWS_ARTICLE['summary'],
                self::DEFAULT_NEWS_ARTICLE['body'],
            );
            $this->entityManager->persist($article);
        }

        $article
            ->setSummary(self::DEFAULT_NEWS_ARTICLE['summary'])
            ->setBody(self::DEFAULT_NEWS_ARTICLE['body'])
            ->setCategory(NewsCategory::GENERAL)
            ->setPublishedAt(new \DateTimeImmutable())
            ->publish()
            ->pin()
            ->unarchive();

        $this->entityManager->flush();

        $io->success('Första nyheten skapad/uppdaterad.');
    }

    private function ensureRequiredAdminAccounts(SymfonyStyle $io): void
    {
        $io->section('Sakerstaller obligatoriska admin-konton');

        $result = $this->systemAccountProvisioner->ensureRequiredAdminAccounts();

        if (!$result->changed()) {
            $io->success('Reserv-superadmin och vanligt admin-konto finns redan.');
        } else {
            $io->success(sprintf(
                'Admin-konton skapade/uppdaterade: %s',
                implode(', ', array_merge($result->createdEmails(), $result->updatedEmails())),
            ));
        }

    }
}
