<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Ticket\Entity\Ticket;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TicketAttachmentStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{
     *     displayName: string,
     *     filePath: string,
     *     mimeType: ?string,
     *     fileSize: int
     * }
     */
    public function storeUploadedFile(UploadedFile $uploadedFile, Ticket $ticket, string $storagePath): array
    {
        $targetDirectory = $this->resolveStorageDirectory($storagePath, $ticket);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException('Kunde inte skapa lagringsmappen för bilagor.');
        }

        $originalName = trim($uploadedFile->getClientOriginalName());
        $safeOriginalName = '' !== $originalName ? $originalName : 'bilaga';
        $extension = $uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: pathinfo($safeOriginalName, \PATHINFO_EXTENSION);
        $storedName = sprintf(
            '%s%s',
            bin2hex(random_bytes(12)),
            '' !== $extension ? '.'.mb_strtolower((string) $extension) : '',
        );

        $uploadedFile->move($targetDirectory, $storedName);
        $filePath = $targetDirectory.\DIRECTORY_SEPARATOR.$storedName;

        return [
            'displayName' => $safeOriginalName,
            'filePath' => $filePath,
            'mimeType' => $uploadedFile->getClientMimeType() ?: $uploadedFile->getMimeType(),
            'fileSize' => (int) filesize($filePath),
        ];
    }

    /**
     * @return array{
     *     displayName: string,
     *     filePath: string,
     *     mimeType: ?string,
     *     fileSize: int
     * }
     */
    public function storeBinaryContent(string $content, string $displayName, ?string $mimeType, Ticket $ticket, string $storagePath): array
    {
        $targetDirectory = $this->resolveStorageDirectory($storagePath, $ticket);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException('Kunde inte skapa lagringsmappen för bilagor.');
        }

        $safeOriginalName = '' !== trim($displayName) ? trim($displayName) : 'bilaga';
        $extension = pathinfo($safeOriginalName, \PATHINFO_EXTENSION);
        $storedName = sprintf(
            '%s%s',
            bin2hex(random_bytes(12)),
            '' !== $extension ? '.'.mb_strtolower((string) $extension) : '',
        );

        $filePath = $targetDirectory.\DIRECTORY_SEPARATOR.$storedName;
        file_put_contents($filePath, $content);

        return [
            'displayName' => $safeOriginalName,
            'filePath' => $filePath,
            'mimeType' => $mimeType,
            'fileSize' => (int) filesize($filePath),
        ];
    }

    private function resolveStorageDirectory(string $storagePath, Ticket $ticket): string
    {
        $normalizedPath = trim($storagePath);
        if ('' === $normalizedPath) {
            $normalizedPath = 'var/ticket_attachments';
        }

        if (!$this->isAbsolutePath($normalizedPath)) {
            $normalizedPath = rtrim($this->projectDir, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.ltrim($normalizedPath, \DIRECTORY_SEPARATOR);
        }

        return rtrim($normalizedPath, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.strtolower($ticket->getReference());
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
