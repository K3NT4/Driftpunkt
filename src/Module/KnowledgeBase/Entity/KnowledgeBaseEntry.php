<?php

declare(strict_types=1);

namespace App\Module\KnowledgeBase\Entity;

use App\Module\Identity\Entity\User;
use App\Module\KnowledgeBase\Enum\KnowledgeBaseAudience;
use App\Module\KnowledgeBase\Enum\KnowledgeBaseEntryType;
use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'knowledge_base_entries')]
#[ORM\HasLifecycleCallbacks]
class KnowledgeBaseEntry
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(enumType: KnowledgeBaseEntryType::class)]
    private KnowledgeBaseEntryType $type;

    #[ORM\Column(enumType: KnowledgeBaseAudience::class)]
    private KnowledgeBaseAudience $audience;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $author = null;

    public function __construct(
        string $title,
        string $body,
        KnowledgeBaseEntryType $type,
        KnowledgeBaseAudience $audience,
    ) {
        $this->title = trim($title);
        $this->body = trim($body);
        $this->type = $type;
        $this->audience = $audience;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = trim($body);

        return $this;
    }

    public function getType(): KnowledgeBaseEntryType
    {
        return $this->type;
    }

    public function setType(KnowledgeBaseEntryType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getAudience(): KnowledgeBaseAudience
    {
        return $this->audience;
    }

    public function setAudience(KnowledgeBaseAudience $audience): self
    {
        $this->audience = $audience;

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = max(0, $sortOrder);

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }
}
