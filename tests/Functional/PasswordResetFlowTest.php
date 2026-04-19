<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\PasswordResetRequest;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetFlowTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $this->entityManager->clear();
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        $this->entityManager->getConnection()->close();

        parent::tearDown();
        self::ensureKernelShutdown();
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }

    public function testUserCanRequestPasswordResetWithoutLeakingAccountExistence(): void
    {
        $email = sprintf('customer-%s@example.test', bin2hex(random_bytes(4)));
        $user = new User($email, 'Klara', 'Kund', UserType::CUSTOMER);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'InitialPassword123'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/forgot-password?role=customer');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Glömt lösenord?', $crawler->filter('h2')->text());

        $this->client->submitForm('Skicka återställningslänk', [
            'email' => $email,
        ]);

        self::assertResponseRedirects('/forgot-password?role=customer');
        self::assertEmailCount(1);

        $email = $this->getMailerMessage();
        self::assertEmailTextBodyContains($email, '/reset-password/');

        $this->client->followRedirect();

        self::assertAnySelectorTextContains('.notice.success', 'Om adressen finns registrerad');

        $request = $this->entityManager->getRepository(PasswordResetRequest::class)->findOneBy([
            'user' => $user,
        ]);

        self::assertNotNull($request);
    }

    public function testUnknownEmailShowsSameResponseAndDoesNotSendEmail(): void
    {
        $this->client->enableProfiler();
        $this->client->request('GET', '/forgot-password');

        $this->client->submitForm('Skicka återställningslänk', [
            'email' => 'missing@example.test',
        ]);

        self::assertResponseRedirects('/forgot-password?role=customer');
        $this->client->followRedirect();

        self::assertAnySelectorTextContains('.notice.success', 'Om adressen finns registrerad');
        self::assertEmailCount(0);
        self::assertCount(0, $this->entityManager->getRepository(PasswordResetRequest::class)->findAll());
    }

    public function testUserCanResetPasswordWithValidToken(): void
    {
        $email = sprintf('reset-%s@example.test', bin2hex(random_bytes(4)));
        $user = new User($email, 'Runa', 'Reset', UserType::CUSTOMER);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'InitialPassword123'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->enableProfiler();
        $this->client->request('GET', '/forgot-password');
        $this->client->submitForm('Skicka återställningslänk', [
            'email' => $email,
        ]);

        $email = $this->getMailerMessage();
        preg_match('#https?://[^\\s]+/reset-password/([a-f0-9]+)#', $email->getTextBody() ?? '', $matches);
        self::assertArrayHasKey(1, $matches);

        $token = $matches[1];
        $crawler = $this->client->request('GET', '/reset-password/'.$token);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($user->getEmail(), $crawler->filter('p')->first()->text());

        $this->client->submitForm('Spara nytt lösenord', [
            'password' => 'NyttSupersakert123',
            'password_confirm' => 'NyttSupersakert123',
        ]);

        self::assertResponseRedirects('/login?role=customer');
        $this->client->followRedirect();

        self::assertAnySelectorTextContains('.notice.success', 'Ditt lösenord är uppdaterat');

        $this->entityManager->clear();
        $updatedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        self::assertNotNull($updatedUser);
        self::assertTrue($this->passwordHasher->isPasswordValid($updatedUser, 'NyttSupersakert123'));

        $resetRequest = $this->entityManager->getRepository(PasswordResetRequest::class)->findAll()[0] ?? null;
        self::assertInstanceOf(PasswordResetRequest::class, $resetRequest);
        self::assertNotNull($resetRequest->getUsedAt());
    }
}
