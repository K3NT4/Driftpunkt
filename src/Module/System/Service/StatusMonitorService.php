<?php

declare(strict_types=1);

namespace App\Module\System\Service;

use App\Module\Maintenance\Service\MaintenanceMode;

final class StatusMonitorService
{
    private const CACHE_TTL_SECONDS = 180;
    private const CACHE_VERSION = '2026-04-19-downdetector-cloudflare-v1';

    public function __construct(
        private readonly SystemSettings $systemSettings,
        private readonly MaintenanceMode $maintenanceMode,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{
     *     monitors: list<array{
     *         type: string,
     *         name: string,
     *         target: string,
     *         details: ?string,
     *         linkLabel: ?string,
     *         linkUrl: ?string,
     *         icon: string,
     *         showOnHomepage: bool
     *     }>,
     *     rawLines: list<string>
     * }
     */
    public function getSettings(): array
    {
        return $this->systemSettings->getStatusMonitorSettings();
    }

    /**
     * @return list<array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }>
     */
    public function getPublicStatuses(): array
    {
        $settings = $this->getSettings();
        $siteStatus = $this->buildSiteStatusCard();

        if ([] !== $settings['monitors']) {
            return array_merge(
                [$siteStatus],
                $this->removeSiteStatusDuplicates($this->resolveMonitorStatuses($settings['monitors'])),
            );
        }

        return array_merge([$siteStatus], $this->removeSiteStatusDuplicates(array_map(
            static fn (array $item): array => [
                'name' => $item['name'],
                'icon' => $item['icon'],
                'status' => $item['status'],
                'stateLabel' => $item['stateLabel'],
                'linkLabel' => $item['linkLabel'],
                'url' => $item['url'],
                'pill' => $item['pill'],
                'health' => 'green',
                'statusSymbol' => '✓',
                'checkedAt' => 'Sparad manuellt',
                'target' => $item['url'] ?? $item['name'],
                'type' => 'manual',
                'showOnHomepage' => true,
            ],
            $this->systemSettings->getPublicStatusSettings()['items'],
        )));
    }

    /**
     * @param list<array{
     *     type: string,
     *     name: string,
     *     target: string,
     *     details: ?string,
     *     linkLabel: ?string,
     *     linkUrl: ?string,
     *     icon: string,
     *     showOnHomepage: bool
     * }> $monitors
     * @return list<array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }>
     */
    private function resolveMonitorStatuses(array $monitors): array
    {
        $cacheKey = hash('sha256', self::CACHE_VERSION.'|'.json_encode($monitors, \JSON_THROW_ON_ERROR));
        $cached = $this->readCache($cacheKey);
        if (null !== $cached) {
            return $cached;
        }

        $statuses = [];
        $checkedAt = (new \DateTimeImmutable())->format('Y-m-d H:i');

        foreach ($monitors as $monitor) {
            $statuses[] = match ($monitor['type']) {
                'url' => $this->buildUrlStatus($monitor, $checkedAt),
                'host' => $this->buildHostStatus($monitor, $checkedAt),
                'downdetector' => $this->buildDowndetectorStatus($monitor, $checkedAt),
                'isitdownrightnow' => $this->buildIsItDownRightNowStatus($monitor, $checkedAt),
                default => $this->buildManualStatus($monitor, $checkedAt),
            };
        }

        $this->writeCache($cacheKey, $statuses);

        return $statuses;
    }

    /**
     * @return array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }
     */
    private function buildSiteStatusCard(): array
    {
        $state = $this->maintenanceMode->getState();
        $checkedAt = (new \DateTimeImmutable())->format('Y-m-d H:i');

        if ((bool) ($state['effectiveEnabled'] ?? false)) {
            return [
                'name' => 'Driftpunkt',
                'icon' => 'display',
                'status' => 'Underhåll pågår',
                'stateLabel' => $state['message'] ?: 'Kund- och teknikerinloggning är tillfälligt pausad',
                'linkLabel' => 'Öppna driftstatus',
                'url' => '/driftstatus',
                'pill' => 'Driftpunkt',
                'health' => 'red',
                'statusSymbol' => '!',
                'checkedAt' => $checkedAt,
                'target' => '/driftstatus',
                'type' => 'site',
                'showOnHomepage' => true,
            ];
        }

        if ((bool) ($state['isUpcoming'] ?? false)) {
            return [
                'name' => 'Driftpunkt',
                'icon' => 'display',
                'status' => 'Underhåll planerat',
                'stateLabel' => $state['message'] ?: (($state['statusLabel'] ?? 'Schemalagt underhåll väntar').' · senast kontrollerad '.$checkedAt),
                'linkLabel' => 'Öppna driftstatus',
                'url' => '/driftstatus',
                'pill' => 'Driftpunkt',
                'health' => 'yellow',
                'statusSymbol' => '•',
                'checkedAt' => $checkedAt,
                'target' => '/driftstatus',
                'type' => 'site',
                'showOnHomepage' => true,
            ];
        }

        return [
            'name' => 'Driftpunkt',
            'icon' => 'display',
            'status' => 'Allt uppe',
            'stateLabel' => 'Normal drift · senast kontrollerad '.$checkedAt,
            'linkLabel' => 'Öppna driftstatus',
            'url' => '/driftstatus',
            'pill' => 'Driftpunkt',
            'health' => 'green',
            'statusSymbol' => '✓',
            'checkedAt' => $checkedAt,
            'target' => '/driftstatus',
            'type' => 'site',
            'showOnHomepage' => true,
        ];
    }

    /**
     * @param list<array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }> $items
     * @return list<array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }>
     */
    private function removeSiteStatusDuplicates(array $items): array
    {
        return array_values(array_filter(
            $items,
            static fn (array $item): bool => 'driftpunkt' !== mb_strtolower(trim((string) ($item['name'] ?? ''))),
        ));
    }

    /**
     * @param array{
     *     type: string,
     *     name: string,
     *     target: string,
     *     details: ?string,
     *     linkLabel: ?string,
     *     linkUrl: ?string,
     *     icon: string,
     *     showOnHomepage: bool
     * } $monitor
     * @return array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }
     */
    private function buildManualStatus(array $monitor, string $checkedAt): array
    {
        return $this->formatStatusCard(
            $monitor,
            'green',
            $monitor['details'] ?? 'Manuellt markerad som uppe',
            $monitor['details'] ?? 'Normal drift',
            'Manuell',
            $checkedAt,
        );
    }

    /**
     * @param array{
     *     type: string,
     *     name: string,
     *     target: string,
     *     details: ?string,
     *     linkLabel: ?string,
     *     linkUrl: ?string,
     *     icon: string,
     *     showOnHomepage: bool
     * } $monitor
     * @return array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }
     */
    private function buildUrlStatus(array $monitor, string $checkedAt): array
    {
        $response = $this->requestUrl($monitor['target']);
        $code = $response['statusCode'];

        if (null === $code) {
            return $this->formatStatusCard(
                $monitor,
                'red',
                'Ingen kontakt med URL',
                $response['error'] ?? 'Kontrollen misslyckades',
                'URL',
                $checkedAt,
            );
        }

        if ($code >= 200 && $code < 400) {
            return $this->formatStatusCard(
                $monitor,
                'green',
                sprintf('Uppe (%d)', $code),
                $monitor['details'] ?? 'Svarar normalt',
                'URL',
                $checkedAt,
            );
        }

        if ($code >= 400 && $code < 500) {
            return $this->formatStatusCard(
                $monitor,
                'yellow',
                sprintf('Svarar med varning (%d)', $code),
                $monitor['details'] ?? 'Tjänsten svarar men returnerar klientfel',
                'URL',
                $checkedAt,
            );
        }

        return $this->formatStatusCard(
            $monitor,
            'red',
            sprintf('Fel från tjänsten (%d)', $code),
            $monitor['details'] ?? 'Tjänsten svarar med serverfel',
            'URL',
            $checkedAt,
        );
    }

    /**
     * @param array{
     *     type: string,
     *     name: string,
     *     target: string,
     *     details: ?string,
     *     linkLabel: ?string,
     *     linkUrl: ?string,
     *     icon: string,
     *     showOnHomepage: bool
     * } $monitor
     * @return array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }
     */
    private function buildHostStatus(array $monitor, string $checkedAt): array
    {
        [$host, $port] = $this->parseHostTarget($monitor['target']);

        if (null === $host || null === $port) {
            return $this->formatStatusCard(
                $monitor,
                'yellow',
                'Ogiltigt mål',
                'Ange host eller IP som host:port',
                'Host',
                $checkedAt,
            );
        }

        $errno = 0;
        $errstr = '';
        $connection = @fsockopen($host, $port, $errno, $errstr, 4.0);

        if (\is_resource($connection)) {
            fclose($connection);

            return $this->formatStatusCard(
                $monitor,
                'green',
                sprintf('Port %d svarar', $port),
                $monitor['details'] ?? sprintf('Kontakt med %s:%d lyckades', $host, $port),
                'Host',
                $checkedAt,
            );
        }

        return $this->formatStatusCard(
            $monitor,
            'red',
            sprintf('Ingen kontakt på %s:%d', $host, $port),
            '' !== trim($errstr) ? $errstr : 'Hostkontrollen misslyckades',
            'Host',
            $checkedAt,
        );
    }

    /**
     * @param array{
     *     type: string,
     *     name: string,
     *     target: string,
     *     details: ?string,
     *     linkLabel: ?string,
     *     linkUrl: ?string,
     *     icon: string,
     *     showOnHomepage: bool
     * } $monitor
     * @return array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }
     */
    private function buildDowndetectorStatus(array $monitor, string $checkedAt): array
    {
        $response = $this->requestUrl($monitor['target']);
        $content = mb_strtolower($response['body'] ?? '');

        if (null === $response['statusCode'] || '' === $content) {
            return $this->formatStatusCard(
                $monitor,
                'yellow',
                'Kunde inte läsa extern statussida',
                $response['error'] ?? 'Försök igen senare',
                'Downdetector',
                $checkedAt,
            );
        }

        if ($this->isCloudflareChallengePage($content)) {
            return $this->formatStatusCard(
                $monitor,
                'yellow',
                'Extern kontroll blockerad',
                'Downdetector kraver JavaScript eller cookies for att visa status just nu. Testa isitdownrightnow om motsvarande sida finns dar.',
                'Downdetector',
                $checkedAt,
            );
        }

        $downdetectorState = $this->detectDowndetectorState($content);

        if ('green' === $downdetectorState) {
            return $this->formatStatusCard(
                $monitor,
                'green',
                'Allt uppe',
                $monitor['details'] ?? 'Inga aktuella störningar rapporteras',
                'Downdetector',
                $checkedAt,
            );
        }

        if ('yellow' === $downdetectorState) {
            return $this->formatStatusCard(
                $monitor,
                'yellow',
                'Störningar rapporteras',
                $monitor['details'] ?? 'Användarrapporter indikerar störningar',
                'Downdetector',
                $checkedAt,
            );
        }

        return $this->formatStatusCard(
            $monitor,
            'yellow',
            'Oklart läge',
            $monitor['details'] ?? 'Kunde inte tolka extern status säkert',
            'Downdetector',
            $checkedAt,
        );
    }

    /**
     * @param array{
     *     type: string,
     *     name: string,
     *     target: string,
     *     details: ?string,
     *     linkLabel: ?string,
     *     linkUrl: ?string,
     *     icon: string,
     *     showOnHomepage: bool
     * } $monitor
     * @return array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }
     */
    private function buildIsItDownRightNowStatus(array $monitor, string $checkedAt): array
    {
        $response = $this->requestUrl($monitor['target']);
        $content = mb_strtolower($response['body'] ?? '');

        if (null === $response['statusCode'] || '' === $content) {
            return $this->formatStatusCard(
                $monitor,
                'yellow',
                'Kunde inte läsa extern statussida',
                $response['error'] ?? 'Försök igen senare',
                'IsItDownRightNow',
                $checkedAt,
            );
        }

        $state = $this->detectIsItDownRightNowState($content);

        if ('green' === $state) {
            return $this->formatStatusCard(
                $monitor,
                'green',
                'Allt uppe',
                $monitor['details'] ?? 'IsItDownRightNow rapporterar att tjänsten svarar',
                'IsItDownRightNow',
                $checkedAt,
            );
        }

        if ('red' === $state) {
            return $this->formatStatusCard(
                $monitor,
                'red',
                'Tjänsten verkar nere',
                $monitor['details'] ?? 'IsItDownRightNow rapporterar att servern är nere',
                'IsItDownRightNow',
                $checkedAt,
            );
        }

        return $this->formatStatusCard(
            $monitor,
            'yellow',
            'Oklart läge',
            $monitor['details'] ?? 'Kunde inte tolka IsItDownRightNow säkert',
            'IsItDownRightNow',
            $checkedAt,
        );
    }

    private function detectDowndetectorState(string $content): string
    {
        if (
            str_contains($content, 'user reports show no current problems')
            || str_contains($content, 'no current problems')
        ) {
            return 'green';
        }

        if (
            str_contains($content, 'user reports indicate')
            || str_contains($content, 'user reports show problems')
            || str_contains($content, 'there may be a disruption')
            || str_contains($content, 'possible problems')
        ) {
            return 'yellow';
        }

        return 'unknown';
    }

    private function detectIsItDownRightNowState(string $content): string
    {
        if (
            str_contains($content, 'server is up')
            || str_contains($content, 'website is up')
            || str_contains($content, 'site is up')
        ) {
            return 'green';
        }

        if (
            str_contains($content, 'server is down')
            || str_contains($content, 'website is down')
            || str_contains($content, 'site is down')
            || str_contains($content, 'site was offline')
        ) {
            return 'red';
        }

        return 'unknown';
    }

    private function isCloudflareChallengePage(string $content): bool
    {
        return str_contains($content, 'just a moment...')
            || str_contains($content, 'enable javascript and cookies to continue')
            || str_contains($content, 'cf_chl_opt')
            || str_contains($content, 'challenge-platform');
    }

    /**
     * @param array{
     *     type: string,
     *     name: string,
     *     target: string,
     *     details: ?string,
     *     linkLabel: ?string,
     *     linkUrl: ?string,
     *     icon: string,
     *     showOnHomepage: bool
     * } $monitor
     * @return array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }
     */
    private function formatStatusCard(
        array $monitor,
        string $health,
        string $status,
        string $stateLabel,
        string $pill,
        string $checkedAt,
    ): array {
        return [
            'name' => $monitor['name'],
            'icon' => $monitor['icon'],
            'status' => $status,
            'stateLabel' => sprintf('%s · senast kontrollerad %s', $stateLabel, $checkedAt),
            'linkLabel' => $monitor['linkLabel'],
            'url' => $monitor['linkUrl'] ?? $monitor['target'],
            'pill' => $pill,
            'health' => $health,
            'statusSymbol' => match ($health) {
                'red' => '!',
                'yellow' => '•',
                default => '✓',
            },
            'checkedAt' => $checkedAt,
            'target' => $monitor['target'],
            'type' => $monitor['type'],
            'showOnHomepage' => $monitor['showOnHomepage'] ?? true,
        ];
    }

    /**
     * @return array{statusCode: ?int, body: ?string, error: ?string}
     */
    private function requestUrl(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'User-Agent: DriftpunktStatusMonitor/1.0',
                    'Accept: text/html,application/json;q=0.9,*/*;q=0.8',
                ]),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $statusCode = $this->extractStatusCode($headers);

        return [
            'statusCode' => $statusCode,
            'body' => false === $body ? null : $body,
            'error' => false === $body && null === $statusCode ? 'Ingen respons från mål-URL' : null,
        ];
    }

    /**
     * @param list<string> $headers
     */
    private function extractStatusCode(array $headers): ?int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function parseHostTarget(string $target): array
    {
        $trimmed = trim($target);
        if ('' === $trimmed) {
            return [null, null];
        }

        if (preg_match('/^\[(.+)\]:(\d+)$/', $trimmed, $matches) === 1) {
            return [$matches[1], (int) $matches[2]];
        }

        $parts = explode(':', $trimmed);
        if (2 !== \count($parts)) {
            return [null, null];
        }

        $host = trim($parts[0]);
        $port = (int) trim($parts[1]);

        if ('' === $host || $port < 1 || $port > 65535) {
            return [null, null];
        }

        return [$host, $port];
    }

    /**
     * @return list<array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }>|null
     */
    private function readCache(string $cacheKey): ?array
    {
        $path = $this->getCachePath();
        if (!is_file($path)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (!\is_array($payload)) {
            return null;
        }

        if (($payload['key'] ?? null) !== $cacheKey) {
            return null;
        }

        $createdAt = (int) ($payload['createdAt'] ?? 0);
        if ($createdAt < (time() - self::CACHE_TTL_SECONDS)) {
            return null;
        }

        $items = $payload['items'] ?? null;

        return \is_array($items) ? $items : null;
    }

    /**
     * @param list<array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string,
     *     health: string,
     *     statusSymbol: string,
     *     checkedAt: string,
     *     target: string,
     *     type: string,
     *     showOnHomepage: bool
     * }> $items
     */
    private function writeCache(string $cacheKey, array $items): void
    {
        $path = $this->getCachePath();
        $directory = \dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        @file_put_contents($path, json_encode([
            'key' => $cacheKey,
            'createdAt' => time(),
            'items' => $items,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
    }

    private function getCachePath(): string
    {
        return $this->projectDir.'/var/cache/status-monitor-cache.json';
    }
}
