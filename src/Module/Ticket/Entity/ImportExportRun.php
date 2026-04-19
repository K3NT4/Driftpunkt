<?php

declare(strict_types=1);

namespace App\Module\Ticket\Entity;

use App\Module\Identity\Entity\User;
use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_export_runs')]
#[ORM\HasLifecycleCallbacks]
class ImportExportRun
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $runType;

    #[ORM\Column(length: 64)]
    private string $sourceSystem;

    #[ORM\Column(length: 180)]
    private string $sourceLabel;

    #[ORM\Column]
    private bool $dryRun = false;

    #[ORM\Column]
    private int $totalItems = 0;

    #[ORM\Column]
    private int $createdItems = 0;

    #[ORM\Column]
    private int $skippedItems = 0;

    #[ORM\Column]
    private int $duplicateItems = 0;

    #[ORM\Column(length: 255)]
    private string $statusMessage;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $details = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $actor = null;

    public function __construct(string $runType, string $sourceSystem, string $sourceLabel, string $statusMessage)
    {
        $this->runType = trim($runType);
        $this->sourceSystem = trim($sourceSystem);
        $this->sourceLabel = trim($sourceLabel);
        $this->statusMessage = trim($statusMessage);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRunType(): string
    {
        return $this->runType;
    }

    public function getSourceSystem(): string
    {
        return $this->sourceSystem;
    }

    public function getSourceLabel(): string
    {
        return $this->sourceLabel;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;

        return $this;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function setTotalItems(int $totalItems): self
    {
        $this->totalItems = max(0, $totalItems);

        return $this;
    }

    public function getCreatedItems(): int
    {
        return $this->createdItems;
    }

    public function setCreatedItems(int $createdItems): self
    {
        $this->createdItems = max(0, $createdItems);

        return $this;
    }

    public function getSkippedItems(): int
    {
        return $this->skippedItems;
    }

    public function setSkippedItems(int $skippedItems): self
    {
        $this->skippedItems = max(0, $skippedItems);

        return $this;
    }

    public function getDuplicateItems(): int
    {
        return $this->duplicateItems;
    }

    public function setDuplicateItems(int $duplicateItems): self
    {
        $this->duplicateItems = max(0, $duplicateItems);

        return $this;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    public function setStatusMessage(string $statusMessage): self
    {
        $this->statusMessage = trim($statusMessage);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * @param array<string, mixed> $details
     */
    public function setDetails(array $details): self
    {
        $this->details = $details;

        return $this;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): self
    {
        $this->actor = $actor;

        return $this;
    }
}
