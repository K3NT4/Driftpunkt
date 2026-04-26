<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketComment;
use App\Module\Ticket\Entity\TicketCommentAttachment;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TicketCommentAttachmentBuilder
{
    public function __construct(
        private readonly TicketAttachmentStorage $ticketAttachmentStorage,
    ) {
    }

    /**
     * @param array{
     *     enabled: bool,
     *     maxUploadMb: int,
     *     storagePath: string,
     *     allowedExtensions: list<string>,
     *     externalUploadsEnabled: bool,
     *     externalProviderLabel: string,
     *     externalInstructions: string
     * } $attachmentSettings
     * @return list<TicketCommentAttachment>
     */
    public function build(
        ?UploadedFile $uploadedFile,
        string $externalAttachmentUrl,
        string $externalAttachmentLabel,
        Ticket $ticket,
        TicketComment $comment,
        array $attachmentSettings,
    ): array {
        if (!$uploadedFile instanceof UploadedFile && '' === $externalAttachmentUrl) {
            return [];
        }

        if (!$attachmentSettings['enabled']) {
            throw new \InvalidArgumentException('Bilagor är inte aktiverade av admin just nu.');
        }

        $attachments = [];
        if ($uploadedFile instanceof UploadedFile) {
            $attachments[] = $this->buildUploadedAttachment($uploadedFile, $ticket, $comment, $attachmentSettings);
        }

        if ('' !== $externalAttachmentUrl) {
            $attachments[] = $this->buildExternalAttachment($externalAttachmentUrl, $externalAttachmentLabel, $comment, $attachmentSettings);
        }

        return $attachments;
    }

    /**
     * @param array{maxUploadMb: int, storagePath: string, allowedExtensions: list<string>} $attachmentSettings
     */
    private function buildUploadedAttachment(UploadedFile $uploadedFile, Ticket $ticket, TicketComment $comment, array $attachmentSettings): TicketCommentAttachment
    {
        if (!$uploadedFile->isValid()) {
            throw new \InvalidArgumentException('Filen kunde inte laddas upp.');
        }

        $maxFileSize = $attachmentSettings['maxUploadMb'] * 1024 * 1024;
        if ($uploadedFile->getSize() > $maxFileSize) {
            throw new \InvalidArgumentException(sprintf('Filen är för stor. Maxstorlek för direktuppladdning är %d MB.', $attachmentSettings['maxUploadMb']));
        }

        $originalName = trim($uploadedFile->getClientOriginalName());
        $extension = ltrim(mb_strtolower(pathinfo($originalName, \PATHINFO_EXTENSION)), '.');
        if ('' === $extension || !\in_array($extension, $attachmentSettings['allowedExtensions'], true)) {
            throw new \InvalidArgumentException(sprintf('Filtypen är inte tillåten. Tillåtna filtyper är: %s.', implode(', ', $attachmentSettings['allowedExtensions'])));
        }

        $storedFile = $this->ticketAttachmentStorage->storeUploadedFile($uploadedFile, $ticket, $attachmentSettings['storagePath']);

        return TicketCommentAttachment::fromLocalFile(
            $comment,
            $storedFile['displayName'],
            $storedFile['filePath'],
            $storedFile['mimeType'],
            $storedFile['fileSize'],
        );
    }

    /**
     * @param array{externalUploadsEnabled: bool, externalProviderLabel: string} $attachmentSettings
     */
    private function buildExternalAttachment(string $externalAttachmentUrl, string $externalAttachmentLabel, TicketComment $comment, array $attachmentSettings): TicketCommentAttachment
    {
        if (!$attachmentSettings['externalUploadsEnabled']) {
            throw new \InvalidArgumentException('Extern delningslänk för stora filer är inte aktiverad av admin.');
        }

        $normalizedUrl = filter_var($externalAttachmentUrl, \FILTER_VALIDATE_URL);
        $scheme = false !== $normalizedUrl ? parse_url($normalizedUrl, \PHP_URL_SCHEME) : null;
        if (false === $normalizedUrl || !\in_array(mb_strtolower((string) $scheme), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Delningslänken måste vara en giltig http- eller https-adress.');
        }

        $displayName = '' !== $externalAttachmentLabel
            ? $externalAttachmentLabel
            : sprintf('Extern fil via %s', $attachmentSettings['externalProviderLabel']);

        return TicketCommentAttachment::fromExternalUrl($comment, mb_strimwidth(trim($displayName), 0, 255, ''), $normalizedUrl);
    }
}
