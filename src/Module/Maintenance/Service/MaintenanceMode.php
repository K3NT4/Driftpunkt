<?php

declare(strict_types=1);

namespace App\Module\Maintenance\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MaintenanceMode
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/maintenance.json')]
        private readonly string $stateFile,
    ) {
    }

    public function isEnabled(?\DateTimeImmutable $now = null): bool
    {
        $state = $this->readState();
        $now ??= new \DateTimeImmutable();

        return $this->isManuallyEnabledState($state) || $this->isScheduledActiveState($state, $now);
    }

    public function enable(?string $message = null): void
    {
        $state = $this->readState();
        $state['enabled'] = true;
        $state['message'] = $this->normalizeMessage($message) ?? 'Systemet är tillfälligt satt i underhållsläge.';
        $state['updatedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);

        $this->writeState($state);
    }

    public function disable(bool $clearSchedule = true): void
    {
        $state = $this->readState();
        $state['enabled'] = false;

        if ($clearSchedule) {
            $state['scheduledStartAt'] = null;
            $state['scheduledEndAt'] = null;
        }

        if ($clearSchedule || !$this->isScheduledConfiguredState($state)) {
            $state['message'] = null;
        }

        $state['updatedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);

        $this->writeState($state);
    }

    public function updateSettings(
        bool $manualEnabled,
        ?string $message,
        ?\DateTimeImmutable $scheduledStartAt,
        ?\DateTimeImmutable $scheduledEndAt,
    ): void {
        $this->writeState([
            'enabled' => $manualEnabled,
            'message' => $this->normalizeMessage($message),
            'scheduledStartAt' => $scheduledStartAt?->format(DATE_ATOM),
            'scheduledEndAt' => $scheduledEndAt?->format(DATE_ATOM),
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function getMessage(): ?string
    {
        return $this->getState()['message'];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     effectiveEnabled: bool,
     *     mode: 'inactive'|'manual'|'scheduled_active'|'scheduled_upcoming',
     *     statusLabel: string,
     *     message: ?string,
     *     updatedAt: ?string,
     *     scheduledStartAt: ?string,
     *     scheduledEndAt: ?string,
     *     hasSchedule: bool,
     *     isUpcoming: bool,
     *     isScheduledActive: bool
     * }
     */
    public function getState(?\DateTimeImmutable $now = null): array
    {
        $rawState = $this->readState();
        $now ??= new \DateTimeImmutable();

        $manualEnabled = $this->isManuallyEnabledState($rawState);
        $scheduledActive = $this->isScheduledActiveState($rawState, $now);
        $scheduledUpcoming = $this->isScheduledUpcomingState($rawState, $now);

        $mode = match (true) {
            $manualEnabled => 'manual',
            $scheduledActive => 'scheduled_active',
            $scheduledUpcoming => 'scheduled_upcoming',
            default => 'inactive',
        };

        return [
            'enabled' => $manualEnabled,
            'effectiveEnabled' => $manualEnabled || $scheduledActive,
            'mode' => $mode,
            'statusLabel' => match ($mode) {
                'manual' => 'Underhållsläge aktivt nu',
                'scheduled_active' => 'Schemalagt underhåll pågår',
                'scheduled_upcoming' => 'Schemalagt underhåll väntar',
                default => 'Normal drift',
            },
            'message' => $this->normalizeMessage($rawState['message'] ?? null),
            'updatedAt' => $this->normalizeDateString($rawState['updatedAt'] ?? null),
            'scheduledStartAt' => $this->normalizeDateString($rawState['scheduledStartAt'] ?? null),
            'scheduledEndAt' => $this->normalizeDateString($rawState['scheduledEndAt'] ?? null),
            'hasSchedule' => $this->isScheduledConfiguredState($rawState),
            'isUpcoming' => $scheduledUpcoming,
            'isScheduledActive' => $scheduledActive,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array
    {
        if (!is_file($this->stateFile)) {
            return $this->defaultState();
        }

        $contents = file_get_contents($this->stateFile);
        if (false === $contents) {
            return $this->defaultState();
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            return array_replace($this->defaultState(), $decoded);
        } catch (\JsonException) {
            return $this->defaultState();
        }
    }

    /**
     * @return array{enabled: bool, message: ?string, updatedAt: ?string, scheduledStartAt: ?string, scheduledEndAt: ?string}
     */
    private function defaultState(): array
    {
        return [
            'enabled' => false,
            'message' => null,
            'updatedAt' => null,
            'scheduledStartAt' => null,
            'scheduledEndAt' => null,
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function writeState(array $state): void
    {
        $dir = \dirname($this->stateFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->stateFile,
            json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, mixed> $state
     */
    private function isManuallyEnabledState(array $state): bool
    {
        return (bool) ($state['enabled'] ?? false);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function isScheduledConfiguredState(array $state): bool
    {
        return $this->parseOptionalDateTime($state['scheduledStartAt'] ?? null) instanceof \DateTimeImmutable;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function isScheduledActiveState(array $state, \DateTimeImmutable $now): bool
    {
        $startsAt = $this->parseOptionalDateTime($state['scheduledStartAt'] ?? null);
        if (!$startsAt instanceof \DateTimeImmutable || $startsAt > $now) {
            return false;
        }

        $endsAt = $this->parseOptionalDateTime($state['scheduledEndAt'] ?? null);

        return !$endsAt instanceof \DateTimeImmutable || $endsAt >= $now;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function isScheduledUpcomingState(array $state, \DateTimeImmutable $now): bool
    {
        $startsAt = $this->parseOptionalDateTime($state['scheduledStartAt'] ?? null);
        if (!$startsAt instanceof \DateTimeImmutable || $startsAt <= $now) {
            return false;
        }

        $endsAt = $this->parseOptionalDateTime($state['scheduledEndAt'] ?? null);

        return !$endsAt instanceof \DateTimeImmutable || $endsAt >= $startsAt;
    }

    private function parseOptionalDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function normalizeMessage(mixed $message): ?string
    {
        return \is_string($message) && '' !== trim($message) ? trim($message) : null;
    }

    private function normalizeDateString(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
