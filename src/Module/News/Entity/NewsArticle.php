<?php

declare(strict_types=1);

namespace App\Module\News\Entity;

use App\Module\Identity\Entity\User;
use App\Module\News\Enum\NewsCategory;
use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'news_articles')]
#[ORM\HasLifecycleCallbacks]
class NewsArticle
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $summary;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $maintenanceStartsAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $maintenanceEndsAt = null;

    #[ORM\Column]
    private bool $isPublished = true;

    #[ORM\Column]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column]
    private bool $isPinned = false;

    #[ORM\Column(enumType: NewsCategory::class, options: ['default' => 'general'])]
    private NewsCategory $category = NewsCategory::GENERAL;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $author = null;

    public function __construct(string $title, string $summary, string $body)
    {
        $this->title = trim($title);
        $this->summary = trim($summary);
        $this->body = trim($body);
        $this->publishedAt = new \DateTimeImmutable();
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

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): self
    {
        $this->summary = trim($summary);

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

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $imageUrl = null !== $imageUrl ? trim($imageUrl) : null;
        $this->imageUrl = '' !== (string) $imageUrl ? $imageUrl : null;

        return $this;
    }

    public function getMaintenanceStartsAt(): ?\DateTimeImmutable
    {
        return $this->maintenanceStartsAt;
    }

    public function setMaintenanceStartsAt(?\DateTimeImmutable $maintenanceStartsAt): self
    {
        $this->maintenanceStartsAt = $maintenanceStartsAt;

        return $this;
    }

    public function getMaintenanceEndsAt(): ?\DateTimeImmutable
    {
        return $this->maintenanceEndsAt;
    }

    public function setMaintenanceEndsAt(?\DateTimeImmutable $maintenanceEndsAt): self
    {
        $this->maintenanceEndsAt = $maintenanceEndsAt;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function publish(): self
    {
        $this->isPublished = true;

        return $this;
    }

    public function unpublish(): self
    {
        $this->isPublished = false;

        return $this;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function pin(): self
    {
        $this->isPinned = true;

        return $this;
    }

    public function unpin(): self
    {
        $this->isPinned = false;

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

    public function getCategory(): NewsCategory
    {
        return $this->category;
    }

    public function setCategory(NewsCategory $category): self
    {
        $this->category = $category;

        return $this;
    }
}
