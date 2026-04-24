<?php

declare(strict_types=1);

namespace App\Module\Identity\Entity;

use App\Module\Identity\Enum\UserType;
use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $publicId;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(length: 80)]
    private string $firstName;

    #[ORM\Column(length: 80)]
    private string $lastName;

    #[ORM\Column(enumType: UserType::class)]
    private UserType $type;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $mfaEnabled = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $mfaSecret = null;

    #[ORM\Column]
    private bool $emailNotificationsEnabled = true;

    #[ORM\Column]
    private bool $passwordChangeRequired = false;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'users')]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?Company $company = null;

    #[ORM\ManyToOne(targetEntity: TechnicianTeam::class, inversedBy: 'members')]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?TechnicianTeam $technicianTeam = null;

    public function __construct(
        string $email,
        string $firstName,
        string $lastName,
        UserType $type,
    ) {
        $this->publicId = Uuid::v7();
        $this->email = mb_strtolower(trim($email));
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->type = $type;
        $this->password = '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = [...$this->roles, ...$this->type->defaultRoles()];
        $roles[] = 'ROLE_USER';

        $roles = array_values(array_unique($roles));
        sort($roles);

        return $roles;
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = array_values(array_unique($roles));

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getDisplayName(): string
    {
        return trim(sprintf('%s %s', $this->firstName, $this->lastName));
    }

    public function getType(): UserType
    {
        return $this->type;
    }

    public function setType(UserType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activate(): self
    {
        $this->isActive = true;

        return $this;
    }

    public function deactivate(): self
    {
        $this->isActive = false;

        return $this;
    }

    public function isMfaEnabled(): bool
    {
        return $this->mfaEnabled;
    }

    public function enableMfa(): self
    {
        $this->mfaEnabled = true;

        return $this;
    }

    public function disableMfa(): self
    {
        $this->mfaEnabled = false;

        return $this;
    }

    public function getMfaSecret(): ?string
    {
        return $this->mfaSecret;
    }

    public function setMfaSecret(?string $mfaSecret): self
    {
        $normalizedSecret = null !== $mfaSecret ? strtoupper(trim($mfaSecret)) : null;
        $this->mfaSecret = '' !== (string) $normalizedSecret ? $normalizedSecret : null;

        return $this;
    }

    public function clearMfaSecret(): self
    {
        $this->mfaSecret = null;

        return $this;
    }

    public function isEmailNotificationsEnabled(): bool
    {
        return $this->emailNotificationsEnabled;
    }

    public function enableEmailNotifications(): self
    {
        $this->emailNotificationsEnabled = true;

        return $this;
    }

    public function disableEmailNotifications(): self
    {
        $this->emailNotificationsEnabled = false;

        return $this;
    }

    public function isPasswordChangeRequired(): bool
    {
        return $this->passwordChangeRequired;
    }

    public function requirePasswordChange(): self
    {
        $this->passwordChangeRequired = true;

        return $this;
    }

    public function clearPasswordChangeRequired(): self
    {
        $this->passwordChangeRequired = false;

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getTechnicianTeam(): ?TechnicianTeam
    {
        return $this->technicianTeam;
    }

    public function setTechnicianTeam(?TechnicianTeam $technicianTeam): self
    {
        $this->technicianTeam = $technicianTeam;

        return $this;
    }
}
