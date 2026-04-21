<?php

declare(strict_types=1);

namespace App\Module\System\Entity;

use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'addon_modules')]
#[ORM\UniqueConstraint(name: 'uniq_addon_modules_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class AddonModule
{
    use TimestampableTrait;

    public const INSTALL_STATUS_PLANNED = 'planned';
    public const INSTALL_STATUS_CONFIGURING = 'configuring';
    public const INSTALL_STATUS_INSTALLED = 'installed';
    public const INSTALL_STATUS_BLOCKED = 'blocked';
    public const HEALTH_STATUS_UNKNOWN = 'unknown';
    public const HEALTH_STATUS_HEALTHY = 'healthy';
    public const HEALTH_STATUS_WARNING = 'warning';
    public const HEALTH_STATUS_FAILING = 'failing';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $slug;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adminRoute = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $sourceLabel = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 32, options: ['default' => self::INSTALL_STATUS_PLANNED])]
    private string $installStatus = self::INSTALL_STATUS_PLANNED;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $dependencies = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $environmentVariables = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $setupChecklist = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $impactAreas = null;

    #[ORM\Column(length: 32, options: ['default' => self::HEALTH_STATUS_UNKNOWN])]
    private string $healthStatus = self::HEALTH_STATUS_UNKNOWN;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $releasedAt = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $releasedByEmail = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isEnabled = false;

    public function __construct(string $slug, string $name, string $description)
    {
        $this->slug = self::normalizeSlug($slug);
        $this->name = trim($name);
        $this->description = trim($description);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function renameSlug(string $slug): self
    {
        $this->slug = self::normalizeSlug($slug);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function describe(string $description): self
    {
        $this->description = trim($description);

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $normalized = null !== $version ? trim($version) : null;
        $this->version = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getAdminRoute(): ?string
    {
        return $this->adminRoute;
    }

    public function setAdminRoute(?string $adminRoute): self
    {
        $normalized = null !== $adminRoute ? trim($adminRoute) : null;
        $this->adminRoute = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getSourceLabel(): ?string
    {
        return $this->sourceLabel;
    }

    public function setSourceLabel(?string $sourceLabel): self
    {
        $normalized = null !== $sourceLabel ? trim($sourceLabel) : null;
        $this->sourceLabel = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $normalized = null !== $notes ? trim($notes) : null;
        $this->notes = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getInstallStatus(): string
    {
        return $this->installStatus;
    }

    public function setInstallStatus(string $installStatus): self
    {
        $normalized = mb_strtolower(trim($installStatus));
        if (!\in_array($normalized, self::allowedInstallStatuses(), true)) {
            $normalized = self::INSTALL_STATUS_PLANNED;
        }

        $this->installStatus = $normalized;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getDependenciesList(): array
    {
        return self::explodeLines($this->dependencies);
    }

    /**
     * @param list<string> $dependencies
     */
    public function setDependenciesList(array $dependencies): self
    {
        $this->dependencies = self::implodeLines($dependencies);

        return $this;
    }

    public function getDependencies(): ?string
    {
        return $this->dependencies;
    }

    public function setDependencies(?string $dependencies): self
    {
        $this->dependencies = self::implodeLines(self::explodeLines($dependencies));

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEnvironmentVariablesList(): array
    {
        return self::explodeLines($this->environmentVariables);
    }

    /**
     * @param list<string> $environmentVariables
     */
    public function setEnvironmentVariablesList(array $environmentVariables): self
    {
        $this->environmentVariables = self::implodeLines($environmentVariables);

        return $this;
    }

    public function getEnvironmentVariables(): ?string
    {
        return $this->environmentVariables;
    }

    public function setEnvironmentVariables(?string $environmentVariables): self
    {
        $this->environmentVariables = self::implodeLines(self::explodeLines($environmentVariables));

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSetupChecklistList(): array
    {
        return self::explodeLines($this->setupChecklist);
    }

    /**
     * @param list<string> $setupChecklist
     */
    public function setSetupChecklistList(array $setupChecklist): self
    {
        $this->setupChecklist = self::implodeLines($setupChecklist);

        return $this;
    }

    public function getSetupChecklist(): ?string
    {
        return $this->setupChecklist;
    }

    public function setSetupChecklist(?string $setupChecklist): self
    {
        $this->setupChecklist = self::implodeLines(self::explodeLines($setupChecklist));

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getImpactAreasList(): array
    {
        return self::explodeLines($this->impactAreas);
    }

    /**
     * @param list<string> $impactAreas
     */
    public function setImpactAreasList(array $impactAreas): self
    {
        $this->impactAreas = self::implodeLines($impactAreas);

        return $this;
    }

    public function getImpactAreas(): ?string
    {
        return $this->impactAreas;
    }

    public function setImpactAreas(?string $impactAreas): self
    {
        $this->impactAreas = self::implodeLines(self::explodeLines($impactAreas));

        return $this;
    }

    public function getHealthStatus(): string
    {
        return $this->healthStatus;
    }

    public function setHealthStatus(string $healthStatus): self
    {
        $normalized = mb_strtolower(trim($healthStatus));
        if (!\in_array($normalized, self::allowedHealthStatuses(), true)) {
            $normalized = self::HEALTH_STATUS_UNKNOWN;
        }

        $this->healthStatus = $normalized;

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): self
    {
        $this->verifiedAt = $verifiedAt;

        return $this;
    }

    public function getReleasedAt(): ?\DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function setReleasedAt(?\DateTimeImmutable $releasedAt): self
    {
        $this->releasedAt = $releasedAt;

        return $this;
    }

    public function getReleasedByEmail(): ?string
    {
        return $this->releasedByEmail;
    }

    public function setReleasedByEmail(?string $releasedByEmail): self
    {
        $normalized = null !== $releasedByEmail ? mb_strtolower(trim($releasedByEmail)) : null;
        $this->releasedByEmail = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function isReleased(): bool
    {
        return $this->releasedAt instanceof \DateTimeImmutable && null !== $this->releasedByEmail;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->isEnabled = $enabled;

        return $this;
    }

    public static function normalizeSlug(string $slug): string
    {
        $normalized = mb_strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }

    /**
     * @return list<string>
     */
    public static function allowedInstallStatuses(): array
    {
        return [
            self::INSTALL_STATUS_PLANNED,
            self::INSTALL_STATUS_CONFIGURING,
            self::INSTALL_STATUS_INSTALLED,
            self::INSTALL_STATUS_BLOCKED,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedHealthStatuses(): array
    {
        return [
            self::HEALTH_STATUS_UNKNOWN,
            self::HEALTH_STATUS_HEALTHY,
            self::HEALTH_STATUS_WARNING,
            self::HEALTH_STATUS_FAILING,
        ];
    }

    /**
     * @return list<string>
     */
    private static function explodeLines(?string $value): array
    {
        if (null === $value) {
            return [];
        }

        $parts = preg_split('/\R+/', trim($value)) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $part): string => trim($part),
            $parts,
        ), static fn (string $part): bool => '' !== $part));
    }

    /**
     * @param list<string> $lines
     */
    private static function implodeLines(array $lines): ?string
    {
        $normalized = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines,
        ), static fn (string $line): bool => '' !== $line));

        return [] !== $normalized ? implode("\n", $normalized) : null;
    }
}
