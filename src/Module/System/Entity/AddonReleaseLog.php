<?php

declare(strict_types=1);

namespace App\Module\System\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'addon_release_logs')]
class AddonReleaseLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AddonModule::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AddonModule $addon;

    #[ORM\Column(length: 180)]
    private string $releasedByEmail;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(type: 'text')]
    private string $summary;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $releaseNotes = null;

    #[ORM\Column]
    private \DateTimeImmutable $releasedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $revokedByEmail = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $revokeNotes = null;

    public function __construct(AddonModule $addon, string $releasedByEmail, ?string $version, string $summary, ?string $releaseNotes = null)
    {
        $this->addon = $addon;
        $this->releasedByEmail = mb_strtolower(trim($releasedByEmail));
        $normalizedVersion = null !== $version ? trim($version) : null;
        $this->version = '' !== (string) $normalizedVersion ? $normalizedVersion : null;
        $this->summary = trim($summary);
        $normalizedReleaseNotes = null !== $releaseNotes ? trim($releaseNotes) : null;
        $this->releaseNotes = '' !== (string) $normalizedReleaseNotes ? $normalizedReleaseNotes : null;
        $this->releasedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAddon(): AddonModule
    {
        return $this->addon;
    }

    public function getReleasedByEmail(): string
    {
        return $this->releasedByEmail;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getReleaseNotes(): ?string
    {
        return $this->releaseNotes;
    }

    public function getReleasedAt(): \DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getRevokedByEmail(): ?string
    {
        return $this->revokedByEmail;
    }

    public function getRevokeNotes(): ?string
    {
        return $this->revokeNotes;
    }

    public function revoke(string $revokedByEmail, string $revokeNotes): self
    {
        $this->revokedAt = new \DateTimeImmutable();
        $this->revokedByEmail = mb_strtolower(trim($revokedByEmail));
        $normalizedNotes = trim($revokeNotes);
        $this->revokeNotes = '' !== $normalizedNotes ? $normalizedNotes : null;

        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof \DateTimeImmutable;
    }
}
