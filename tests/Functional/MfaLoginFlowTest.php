<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\System\Service\SystemSettings;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MfaLoginFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private Google2FA $google2FA;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->google2FA = new Google2FA();

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

    public function testMfaEnabledUserMustCompleteChallengeBeforePortalAccess(): void
    {
        $user = new User('mfa-admin@example.test', 'Maja', 'Admin', UserType::ADMIN);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Supersakert123'));
        $user->enableMfa();
        $user->setMfaSecret($this->google2FA->generateSecretKey());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/login?role=admin');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Logga in')->form([
            '_username' => 'mfa-admin@example.test',
            '_password' => 'Supersakert123',
        ]));

        self::assertResponseRedirects('/mfa');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Verifiera inloggningen', $this->client->getResponse()->getContent() ?? '');

        $this->client->request('GET', '/portal/admin/overview');
        self::assertResponseRedirects('/mfa');

        $this->client->followRedirect();
        $this->client->submitForm('Bekräfta kod', [
            'code' => $this->google2FA->getCurrentOtp((string) $user->getMfaSecret()),
        ]);

        self::assertResponseRedirects('/portal/admin/overview');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testMfaChallengeRejectsInvalidCode(): void
    {
        $user = new User('mfa-invalid@example.test', 'Mira', 'Kod', UserType::ADMIN);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Supersakert123'));
        $user->enableMfa();
        $user->setMfaSecret($this->google2FA->generateSecretKey());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/login?role=admin');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Logga in')->form([
            '_username' => 'mfa-invalid@example.test',
            '_password' => 'Supersakert123',
        ]));

        self::assertResponseRedirects('/mfa');
        $this->client->followRedirect();

        $this->client->submitForm('Bekräfta kod', [
            'code' => '000000',
        ]);

        self::assertResponseRedirects('/mfa');
        $this->client->followRedirect();
        self::assertAnySelectorTextContains('.notice.error', 'Koden är inte giltig');
    }

    public function testAdminPolicyOnlyAllowsMfaButDoesNotForceIt(): void
    {
        static::getContainer()->get(SystemSettings::class)->setBool(SystemSettings::FEATURE_MFA_ADMIN_ENABLED, true);

        $user = new User('policy-admin@example.test', 'Pia', 'Policy', UserType::ADMIN);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Supersakert123'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/login?role=admin');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Logga in')->form([
            '_username' => 'policy-admin@example.test',
            '_password' => 'Supersakert123',
        ]));

        self::assertResponseRedirects('/portal');
    }

    public function testUserWithRequiredPasswordChangeMustUpdatePasswordBeforePortalAccess(): void
    {
        $user = new User('must-change@example.test', 'Maja', 'Byte', UserType::ADMIN);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'OldPassword123'));
        $user->requirePasswordChange();
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/login?role=admin');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Logga in')->form([
            '_username' => 'must-change@example.test',
            '_password' => 'OldPassword123',
        ]));

        self::assertResponseRedirects('/portal/security');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Du behöver byta lösenord', $crawler->html());

        $this->client->request('GET', '/portal/admin/overview');
        self::assertResponseRedirects('/portal/security');

        $crawler = $this->client->request('GET', '/portal/security');
        $this->client->submit($crawler->selectButton('Uppdatera lösenord')->form([
            'current_password' => 'OldPassword123',
            'new_password' => 'NewPassword123',
            'new_password_confirm' => 'NewPassword123',
        ]));

        self::assertResponseRedirects('/portal/security');

        $this->entityManager->clear();
        $updatedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'must-change@example.test']);
        self::assertInstanceOf(User::class, $updatedUser);
        self::assertFalse($updatedUser->isPasswordChangeRequired());
        self::assertTrue($this->passwordHasher->isPasswordValid($updatedUser, 'NewPassword123'));

        $this->client->request('GET', '/portal/admin/overview');
        self::assertResponseIsSuccessful();
    }

    public function testUserCanEnableOwnMfaWhenRoleIsAllowed(): void
    {
        static::getContainer()->get(SystemSettings::class)->setBool(SystemSettings::FEATURE_MFA_ADMIN_ENABLED, true);

        $user = new User('self-service-admin@example.test', 'Sara', 'Saker', UserType::ADMIN);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Supersakert123'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/portal/security');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Bra skydd med liten insats', $crawler->html());

        $this->client->submit($crawler->selectButton('Aktivera MFA')->form());

        self::assertResponseRedirects('/portal/security');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('QR-kod', $this->client->getResponse()->getContent() ?? '');

        $this->entityManager->clear();
        $enabledUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'self-service-admin@example.test']);
        self::assertInstanceOf(User::class, $enabledUser);
        self::assertTrue($enabledUser->isMfaEnabled());
        self::assertNotNull($enabledUser->getMfaSecret());
    }

    public function testAdminWithoutMfaCanEnterPortalButSeesSecurityWarning(): void
    {
        static::getContainer()->get(SystemSettings::class)->setBool(SystemSettings::FEATURE_MFA_ADMIN_ENABLED, true);

        $user = new User('warning-admin@example.test', 'Wera', 'Warning', UserType::ADMIN);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Supersakert123'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/login?role=admin');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Logga in')->form([
            '_username' => 'warning-admin@example.test',
            '_password' => 'Supersakert123',
        ]));

        self::assertResponseRedirects('/portal');

        $this->client->request('GET', '/portal/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.admin-security-warning', 'MFA bör aktiveras snarast');
        self::assertSelectorTextContains('.admin-security-warning', 'Ditt admin-konto saknar aktiv MFA');
    }
}
