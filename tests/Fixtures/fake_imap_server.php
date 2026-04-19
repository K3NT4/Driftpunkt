<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: fake_imap_server.php <port> <fixture-json>\n");
    exit(1);
}

$port = (int) $argv[1];
$fixturePath = $argv[2];
$messages = json_decode((string) file_get_contents($fixturePath), true);

if (!\is_array($messages)) {
    fwrite(STDERR, "Invalid fixture payload.\n");
    exit(1);
}

$server = stream_socket_server(sprintf('tcp://127.0.0.1:%d', $port), $errorCode, $errorMessage);
if (!\is_resource($server)) {
    fwrite(STDERR, sprintf("Could not start fake IMAP server: %s (%d)\n", $errorMessage, $errorCode));
    exit(1);
}

$seen = [];
for ($session = 0; $session < 4; ++$session) {
    $connection = @stream_socket_accept($server, 10);
    if (!\is_resource($connection)) {
        break;
    }

    fwrite($connection, "* OK Fake IMAP ready\r\n");

    while (($line = fgets($connection)) !== false) {
        $line = rtrim($line, "\r\n");
        if (!preg_match('/^(A\d+)\s+(.+)$/', $line, $matches)) {
            continue;
        }

        $tag = $matches[1];
        $command = $matches[2];
        $upper = strtoupper($command);

        if (str_starts_with($upper, 'LOGIN')) {
            fwrite($connection, sprintf("%s OK LOGIN completed\r\n", $tag));
            continue;
        }

        if ('STARTTLS' === $upper) {
            fwrite($connection, sprintf("%s OK STARTTLS completed\r\n", $tag));
            continue;
        }

        if ('SELECT INBOX' === $upper) {
            fwrite($connection, sprintf("* %d EXISTS\r\n", \count($messages)));
            fwrite($connection, sprintf("%s OK [READ-WRITE] SELECT completed\r\n", $tag));
            continue;
        }

        if ('SEARCH UNSEEN' === $upper) {
            $ids = [];
            foreach ($messages as $index => $message) {
                $id = (string) ($index + 1);
                if (!isset($seen[$id])) {
                    $ids[] = $id;
                }
            }

            fwrite($connection, sprintf("* SEARCH%s\r\n", [] !== $ids ? ' '.implode(' ', $ids) : ''));
            fwrite($connection, sprintf("%s OK SEARCH completed\r\n", $tag));
            continue;
        }

        if (preg_match('/^FETCH\s+(\d+)\s+BODY\.PEEK\[\]$/i', $command, $fetchMatches)) {
            $id = (int) $fetchMatches[1];
            $message = $messages[$id - 1] ?? null;
            $rawMessage = (string) ($message['raw_message'] ?? sprintf(
                "From: %s\r\nSubject: %s\r\n\r\n%s",
                $message['from'] ?? 'unknown@example.test',
                $message['subject'] ?? 'No subject',
                $message['body'] ?? '',
            ));

            fwrite($connection, sprintf("* %d FETCH (BODY[] {%d}\r\n", $id, \strlen($rawMessage)));
            fwrite($connection, $rawMessage);
            fwrite($connection, ")\r\n");
            fwrite($connection, sprintf("%s OK FETCH completed\r\n", $tag));
            continue;
        }

        if (preg_match('/^STORE\s+(\d+)\s+\+FLAGS\s+\(\\\\Seen\)$/i', $command, $storeMatches)) {
            $id = (string) $storeMatches[1];
            $seen[$id] = true;
            fwrite($connection, sprintf("* %s FETCH (FLAGS (\\Seen))\r\n", $id));
            fwrite($connection, sprintf("%s OK STORE completed\r\n", $tag));
            continue;
        }

        if ('LOGOUT' === $upper) {
            fwrite($connection, "* BYE Fake IMAP logout\r\n");
            fwrite($connection, sprintf("%s OK LOGOUT completed\r\n", $tag));
            break;
        }

        fwrite($connection, sprintf("%s BAD Unsupported command\r\n", $tag));
    }

    fclose($connection);
}

fclose($server);
