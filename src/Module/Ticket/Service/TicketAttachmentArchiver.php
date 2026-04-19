<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketCommentAttachment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TicketAttachmentArchiver
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function archiveLocalAttachmentsForClosedTicket(Ticket $ticket): int
    {
        $attachments = [];
        foreach ($ticket->getComments() as $comment) {
            foreach ($comment->getAttachments() as $attachment) {
                if ($attachment->isExternal() || $attachment->isArchivedInZip()) {
                    continue;
                }

                $filePath = $attachment->getFilePath();
                if (null === $filePath || !is_file($filePath)) {
                    continue;
                }

                $attachments[] = $attachment;
            }
        }

        if ([] === $attachments) {
            return 0;
        }

        $zipPath = $this->resolveArchivePath($ticket);
        $zipDirectory = dirname($zipPath);
        if (!is_dir($zipDirectory) && !mkdir($zipDirectory, 0775, true) && !is_dir($zipDirectory)) {
            throw new \RuntimeException('Kunde inte skapa mappen för bilagearkiv.');
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath, \ZipArchive::CREATE);
        if (true !== $result) {
            throw new \RuntimeException('Kunde inte skapa zip-arkivet för ticketbilagor.');
        }

        $archivedCount = 0;
        $filesToDelete = [];
        foreach ($attachments as $attachment) {
            $sourcePath = $attachment->getFilePath();
            if (null === $sourcePath || !is_file($sourcePath)) {
                continue;
            }

            $entryName = $this->uniqueEntryName($zip, $attachment);
            if (!$zip->addFile($sourcePath, $entryName)) {
                continue;
            }

            $attachment->markAsArchivedInZip($zipPath, $entryName);
            $filesToDelete[] = $sourcePath;
            ++$archivedCount;
        }

        if (!$zip->close()) {
            throw new \RuntimeException('Kunde inte slutfora zip-arkivet for ticketbilagor.');
        }

        foreach ($filesToDelete as $filePath) {
            @unlink($filePath);
        }

        return $archivedCount;
    }

    public function readArchivedAttachment(TicketCommentAttachment $attachment): ?string
    {
        if (!$attachment->isArchivedInZip()) {
            $filePath = $attachment->getFilePath();

            return null !== $filePath && is_file($filePath) ? file_get_contents($filePath) ?: null : null;
        }

        $zipPath = $attachment->getFilePath();
        $entryName = $attachment->getArchiveEntryName();
        if (null === $zipPath || null === $entryName || !is_file($zipPath)) {
            return null;
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath);
        if (true !== $result) {
            return null;
        }

        $content = $zip->getFromName($entryName);
        $zip->close();

        return false !== $content ? $content : null;
    }

    private function resolveArchivePath(Ticket $ticket): string
    {
        return $this->projectDir.\DIRECTORY_SEPARATOR.'var'.\DIRECTORY_SEPARATOR.'ticket_attachment_archives'.\DIRECTORY_SEPARATOR.strtolower($ticket->getReference()).'.zip';
    }

    private function uniqueEntryName(\ZipArchive $zip, TicketCommentAttachment $attachment): string
    {
        $baseName = $this->sanitizeFileName($attachment->getDisplayName());
        $extension = pathinfo($baseName, \PATHINFO_EXTENSION);
        $nameWithoutExtension = '' !== $extension
            ? substr($baseName, 0, -(\strlen($extension) + 1))
            : $baseName;

        $candidate = $baseName;
        $counter = 1;
        while (false !== $zip->locateName($candidate)) {
            $candidate = sprintf(
                '%s-%d%s',
                $nameWithoutExtension,
                $counter,
                '' !== $extension ? '.'.$extension : '',
            );
            ++$counter;
        }

        return $candidate;
    }

    private function sanitizeFileName(string $fileName): string
    {
        $trimmed = trim($fileName);
        $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '-', $trimmed) ?? 'bilaga';
        $sanitized = trim($sanitized, '-');

        return '' !== $sanitized ? $sanitized : 'bilaga';
    }
}
