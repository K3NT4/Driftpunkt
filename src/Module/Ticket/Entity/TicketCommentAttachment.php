<?php

declare(strict_types=1);

namespace App\Module\Ticket\Entity;

use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ticket_comment_attachments')]
#[ORM\HasLifecycleCallbacks]
class TicketCommentAttachment
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TicketComment $comment;

    #[ORM\Column(length: 255)]
    private string $displayName;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $archiveEntryName = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $externalUrl = null;

    private function __construct(TicketComment $comment, string $displayName)
    {
        $this->comment = $comment;
        $this->displayName = trim($displayName);
    }

    public static function fromLocalFile(
        TicketComment $comment,
        string $displayName,
        string $filePath,
        ?string $mimeType,
        int $fileSize,
    ): self {
        $attachment = new self($comment, $displayName);
        $attachment->filePath = $filePath;
        $attachment->mimeType = null !== $mimeType ? trim($mimeType) : null;
        $attachment->fileSize = max(0, $fileSize);

        return $attachment;
    }

    public static function fromExternalUrl(TicketComment $comment, string $displayName, string $externalUrl): self
    {
        $attachment = new self($comment, $displayName);
        $attachment->externalUrl = trim($externalUrl);

        return $attachment;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getComment(): TicketComment
    {
        return $this->comment;
    }

    public function setComment(TicketComment $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function getArchiveEntryName(): ?string
    {
        return $this->archiveEntryName;
    }

    public function markAsArchivedInZip(string $zipPath, string $entryName): self
    {
        $this->filePath = $zipPath;
        $this->archiveEntryName = trim($entryName);

        return $this;
    }

    public function isArchivedInZip(): bool
    {
        return null !== $this->archiveEntryName;
    }

    public function isExternal(): bool
    {
        return null !== $this->externalUrl;
    }

    public function isPreviewableImage(): bool
    {
        if ($this->isExternal()) {
            return false;
        }

        $mimeType = mb_strtolower((string) $this->mimeType);
        if (str_starts_with($mimeType, 'image/')) {
            return \in_array($mimeType, ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'], true);
        }

        $extension = ltrim(mb_strtolower(pathinfo($this->displayName, \PATHINFO_EXTENSION)), '.');

        return \in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true);
    }

    public function isDocumentLike(): bool
    {
        if ($this->isExternal() || $this->isPreviewableImage()) {
            return false;
        }

        $mimeType = mb_strtolower((string) $this->mimeType);
        if (str_starts_with($mimeType, 'text/') || str_contains($mimeType, 'pdf') || str_contains($mimeType, 'word') || str_contains($mimeType, 'sheet') || str_contains($mimeType, 'excel') || str_contains($mimeType, 'csv')) {
            return true;
        }

        $extension = ltrim(mb_strtolower(pathinfo($this->displayName, \PATHINFO_EXTENSION)), '.');

        return \in_array($extension, ['pdf', 'txt', 'log', 'doc', 'docx', 'xls', 'xlsx', 'csv'], true);
    }

    public function getKindLabel(): string
    {
        if ($this->isExternal()) {
            return 'Extern länk';
        }

        if ($this->isPreviewableImage()) {
            return 'Bild';
        }

        if ($this->isDocumentLike()) {
            return 'Dokument';
        }

        return 'Fil';
    }
}
