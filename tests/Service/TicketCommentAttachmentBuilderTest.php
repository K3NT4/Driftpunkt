<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketComment;
use App\Module\Ticket\Service\TicketAttachmentStorage;
use App\Module\Ticket\Service\TicketCommentAttachmentBuilder;
use PHPUnit\Framework\TestCase;

final class TicketCommentAttachmentBuilderTest extends TestCase
{
    public function testInvalidExternalAttachmentUrlIsRejectedWithoutTypeError(): void
    {
        $builder = new TicketCommentAttachmentBuilder(new TicketAttachmentStorage(sys_get_temp_dir()));
        $ticket = new Ticket('DP-1001', 'Testärende', 'Testsammanfattning');
        $author = new User('customer@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $comment = new TicketComment($ticket, $author, 'Här är en extern bilaga.');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Delningslänken måste vara en giltig http- eller https-adress.');

        $builder->build(
            null,
            'inte-en-url',
            '',
            $ticket,
            $comment,
            [
                'enabled' => true,
                'maxUploadMb' => 25,
                'storagePath' => 'var/ticket_attachments',
                'allowedExtensions' => ['pdf', 'png'],
                'externalUploadsEnabled' => true,
                'externalProviderLabel' => 'extern lagring',
                'externalInstructions' => '',
            ],
        );
    }
}
