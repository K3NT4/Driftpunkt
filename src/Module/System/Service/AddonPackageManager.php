<?php

declare(strict_types=1);

namespace App\Module\System\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AddonPackageManager
{
    /**
     * Addon packages must expose code that fits Driftpunkt's addon conventions.
     *
     * @var list<string>
     */
    private const ALLOWED_TARGET_PREFIXES = [
        'src/Module/',
        'templates/',
        'tests/',
        'docs/',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return list<array{
     *     id: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     sourceLabel: string,
     *     installStatus: string,
     *     healthStatus: string,
     *     enabled: bool,
     *     importedAt: \DateTimeImmutable,
     *     fileCount: int,
     *     installPath: string,
     *     archivePath: string,
     *     filesRoot: string,
     *     dependencies: list<string>,
     *     environmentVariables: list<string>,
     *     setupChecklist: list<string>,
     *     impactAreas: list<string>,
     *     adminRoute: ?string,
     *     notes: ?string,
     *     verifiedAt: ?\DateTimeImmutable,
     *     isActiveVersion: bool
     * }>
     */
    public function listInstalledPackages(): array
    {
        $directory = $this->packagesDirectory();
        if (!is_dir($directory)) {
            return [];
        }

        $packages = [];
        foreach (glob($directory.\DIRECTORY_SEPARATOR.'*'.\DIRECTORY_SEPARATOR.'*'.\DIRECTORY_SEPARATOR.'package-manifest.json') ?: [] as $manifestPath) {
            $manifest = $this->readManifest($manifestPath);
            if (null === $manifest) {
                continue;
            }

            $packages[] = $manifest;
        }

        $activeVersionsBySlug = [];
        foreach ($packages as $package) {
            $slug = $package['slug'];
            if (!isset($activeVersionsBySlug[$slug])) {
                $activeVersionsBySlug[$slug] = $this->readActiveVersion($slug);
            }
        }

        $packages = array_map(function (array $package) use ($activeVersionsBySlug): array {
            $package['isActiveVersion'] = ($activeVersionsBySlug[$package['slug']] ?? null) === $package['version'];

            return $package;
        }, $packages);

        usort(
            $packages,
            static function (array $left, array $right): int {
                if ($left['slug'] === $right['slug']) {
                    return $right['importedAt']->getTimestamp() <=> $left['importedAt']->getTimestamp();
                }

                return strcmp($left['slug'], $right['slug']);
            },
        );

        return $packages;
    }

    /**
     * @return list<array{
     *     id: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     sourceLabel: string,
     *     installStatus: string,
     *     healthStatus: string,
     *     enabled: bool,
     *     importedAt: \DateTimeImmutable,
     *     fileCount: int,
     *     installPath: string,
     *     archivePath: string,
     *     filesRoot: string,
     *     dependencies: list<string>,
     *     environmentVariables: list<string>,
     *     setupChecklist: list<string>,
     *     impactAreas: list<string>,
     *     adminRoute: ?string,
     *     notes: ?string,
     *     verifiedAt: ?\DateTimeImmutable,
     *     isActiveVersion: bool
     * }>
     */
    public function listInstalledPackagesForSlug(string $slug): array
    {
        $normalizedSlug = self::normalizeSlug($slug);

        return array_values(array_filter(
            $this->listInstalledPackages(),
            static fn (array $package): bool => $package['slug'] === $normalizedSlug,
        ));
    }

    /**
     * @return array{
     *     id: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     sourceLabel: string,
     *     installStatus: string,
     *     healthStatus: string,
     *     enabled: bool,
     *     importedAt: \DateTimeImmutable,
     *     fileCount: int,
     *     installPath: string,
     *     archivePath: string,
     *     filesRoot: string,
     *     dependencies: list<string>,
     *     environmentVariables: list<string>,
     *     setupChecklist: list<string>,
     *     impactAreas: list<string>,
     *     adminRoute: ?string,
     *     notes: ?string,
     *     verifiedAt: ?\DateTimeImmutable,
     *     isActiveVersion: bool
     * }|null
     */
    public function findInstalledPackage(string $slug, string $version): ?array
    {
        foreach ($this->listInstalledPackagesForSlug($slug) as $package) {
            if ($package['version'] === trim($version)) {
                return $package;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     id: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     sourceLabel: string,
     *     installStatus: string,
     *     healthStatus: string,
     *     enabled: bool,
     *     importedAt: \DateTimeImmutable,
     *     fileCount: int,
     *     installPath: string,
     *     archivePath: string,
     *     filesRoot: string,
     *     dependencies: list<string>,
     *     environmentVariables: list<string>,
     *     setupChecklist: list<string>,
     *     impactAreas: list<string>,
     *     adminRoute: ?string,
     *     notes: ?string,
     *     verifiedAt: ?\DateTimeImmutable,
     *     isActiveVersion: bool
     * }
     */
    public function activateInstalledPackageVersion(string $slug, string $version): array
    {
        $package = $this->findInstalledPackage($slug, $version);
        if (null === $package) {
            throw new \RuntimeException('Den valda addon-versionen kunde inte hittas i paketlagret.');
        }

        $this->writeActiveVersion($package['slug'], $package['version']);

        $activatedPackage = $this->findInstalledPackage($package['slug'], $package['version']);
        if (null === $activatedPackage) {
            throw new \RuntimeException('Kunde inte aktivera den valda addon-versionen.');
        }

        return $activatedPackage;
    }

    /**
     * @return array{
     *     id: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     sourceLabel: string,
     *     installStatus: string,
     *     healthStatus: string,
     *     enabled: bool,
     *     importedAt: \DateTimeImmutable,
     *     fileCount: int,
     *     installPath: string,
     *     archivePath: string,
     *     filesRoot: string,
     *     dependencies: list<string>,
     *     environmentVariables: list<string>,
     *     setupChecklist: list<string>,
     *     impactAreas: list<string>,
     *     adminRoute: ?string,
     *     notes: ?string,
     *     verifiedAt: ?\DateTimeImmutable
     * }
     */
    public function installUploadedPackage(UploadedFile $uploadedFile): array
    {
        if (!$uploadedFile->isValid()) {
            throw new \RuntimeException('Addon-paketet kunde inte laddas upp korrekt.');
        }

        $extension = mb_strtolower((string) pathinfo((string) $uploadedFile->getClientOriginalName(), \PATHINFO_EXTENSION));
        if ('zip' !== $extension) {
            throw new \RuntimeException('Addon-paketet måste vara en zip-fil.');
        }

        $importId = sprintf('addon_%s_%s', (new \DateTimeImmutable())->format('Ymd_His'), bin2hex(random_bytes(4)));
        $stagingDirectory = $this->stagingDirectory().\DIRECTORY_SEPARATOR.$importId;
        $zipPath = $stagingDirectory.\DIRECTORY_SEPARATOR.'addon.zip';
        $extractDirectory = $stagingDirectory.\DIRECTORY_SEPARATOR.'extracted';

        if (!mkdir($stagingDirectory, 0775, true) && !is_dir($stagingDirectory)) {
            throw new \RuntimeException('Kunde inte skapa stagingmapp för addon-paketet.');
        }

        $uploadedFile->move($stagingDirectory, 'addon.zip');
        $this->extractArchiveSafely($zipPath, $extractDirectory);

        $packageRoot = $this->detectPackageRoot($extractDirectory);
        $manifestPath = $packageRoot.\DIRECTORY_SEPARATOR.'addon.json';
        if (!is_file($manifestPath)) {
            throw new \RuntimeException('Addon-paketet saknar addon.json.');
        }

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, \JSON_THROW_ON_ERROR);
        $normalized = $this->normalizePackageManifest($manifest, $packageRoot);

        $slugDirectory = $this->packagesDirectory().\DIRECTORY_SEPARATOR.$normalized['slug'];
        $versionDirectory = $slugDirectory.\DIRECTORY_SEPARATOR.$normalized['version'];
        $installPath = $versionDirectory.\DIRECTORY_SEPARATOR.'package';
        $archivePath = $versionDirectory.\DIRECTORY_SEPARATOR.'addon.zip';
        $storedManifestPath = $versionDirectory.\DIRECTORY_SEPARATOR.'package-manifest.json';

        $this->removeDirectory($versionDirectory);
        if (!mkdir($versionDirectory, 0775, true) && !is_dir($versionDirectory)) {
            throw new \RuntimeException('Kunde inte skapa installationsmapp för addon-paketet.');
        }

        $this->copyDirectory($normalized['filesRoot'], $installPath);
        if (!copy($zipPath, $archivePath)) {
            throw new \RuntimeException('Kunde inte spara addon-arkivet.');
        }

        $storedManifest = [
            'id' => $importId,
            'slug' => $normalized['slug'],
            'name' => $normalized['name'],
            'description' => $normalized['description'],
            'version' => $normalized['version'],
            'sourceLabel' => $normalized['sourceLabel'],
            'installStatus' => $normalized['installStatus'],
            'healthStatus' => $normalized['healthStatus'],
            'enabled' => $normalized['enabled'],
            'importedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'fileCount' => $normalized['fileCount'],
            'installPath' => $installPath,
            'archivePath' => $archivePath,
            'filesRoot' => $installPath,
            'dependencies' => $normalized['dependencies'],
            'environmentVariables' => $normalized['environmentVariables'],
            'setupChecklist' => $normalized['setupChecklist'],
            'impactAreas' => $normalized['impactAreas'],
            'adminRoute' => $normalized['adminRoute'],
            'notes' => $normalized['notes'],
            'verifiedAt' => $normalized['verifiedAt']?->format(DATE_ATOM),
        ];

        file_put_contents(
            $storedManifestPath,
            json_encode($storedManifest, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR),
        );

        $this->writeActiveVersion($normalized['slug'], $normalized['version']);

        $this->removeDirectory($stagingDirectory);

        $package = $this->normalizeStoredManifest($storedManifest);
        $package['isActiveVersion'] = true;

        return $package;
    }

    private function packagesDirectory(): string
    {
        return $this->projectDir.\DIRECTORY_SEPARATOR.'var'.\DIRECTORY_SEPARATOR.'addon_packages';
    }

    private function stagingDirectory(): string
    {
        return $this->projectDir.\DIRECTORY_SEPARATOR.'var'.\DIRECTORY_SEPARATOR.'addon_package_staging';
    }

    private function activeVersionStatePath(string $slug): string
    {
        return $this->packagesDirectory().\DIRECTORY_SEPARATOR.self::normalizeSlug($slug).\DIRECTORY_SEPARATOR.'active-version.json';
    }

    private function readActiveVersion(string $slug): ?string
    {
        $path = $this->activeVersionStatePath($slug);
        if (!is_file($path)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $version = isset($data['version']) && is_string($data['version']) ? trim($data['version']) : '';

        return '' !== $version ? $version : null;
    }

    private function writeActiveVersion(string $slug, string $version): void
    {
        $slugDirectory = $this->packagesDirectory().\DIRECTORY_SEPARATOR.self::normalizeSlug($slug);
        if (!is_dir($slugDirectory) && !mkdir($slugDirectory, 0775, true) && !is_dir($slugDirectory)) {
            throw new \RuntimeException('Kunde inte skapa state-mapp för addon-versionen.');
        }

        file_put_contents(
            $this->activeVersionStatePath($slug),
            json_encode([
                'slug' => self::normalizeSlug($slug),
                'version' => trim($version),
                'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR),
        );
    }

    private function extractArchiveSafely(string $zipPath, string $targetDirectory): void
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($zipPath)) {
            throw new \RuntimeException('Kunde inte öppna addon-paketet.');
        }

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $entryName = (string) $zip->getNameIndex($index);
            $normalizedEntry = str_replace('\\', '/', $entryName);
            if (str_contains($normalizedEntry, '../') || str_starts_with($normalizedEntry, '/')) {
                $zip->close();

                throw new \RuntimeException('Addon-paketet innehåller ogiltiga sökvägar.');
            }

            $targetPath = $targetDirectory.\DIRECTORY_SEPARATOR.$normalizedEntry;
            if (str_ends_with($normalizedEntry, '/')) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
                    $zip->close();

                    throw new \RuntimeException('Kunde inte skapa mapp under addon-extraktionen.');
                }

                continue;
            }

            $parent = dirname($targetPath);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                $zip->close();

                throw new \RuntimeException('Kunde inte skapa filkatalog för addon-paketet.');
            }

            $content = $zip->getFromIndex($index);
            if (false === $content) {
                $zip->close();

                throw new \RuntimeException('Kunde inte läsa en fil ur addon-paketet.');
            }

            file_put_contents($targetPath, $content);
        }

        $zip->close();
    }

    private function detectPackageRoot(string $extractDirectory): string
    {
        if (is_file($extractDirectory.\DIRECTORY_SEPARATOR.'addon.json')) {
            return $extractDirectory;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractDirectory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || 'addon.json' !== $file->getFilename()) {
                continue;
            }

            return dirname($file->getPathname());
        }

        throw new \RuntimeException('Addon-paketet innehåller ingen igenkännbar addonrot med addon.json.');
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{
     *     slug: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     sourceLabel: string,
     *     installStatus: string,
     *     healthStatus: string,
     *     enabled: bool,
     *     fileCount: int,
     *     filesRoot: string,
     *     dependencies: list<string>,
     *     environmentVariables: list<string>,
     *     setupChecklist: list<string>,
     *     impactAreas: list<string>,
     *     adminRoute: ?string,
     *     notes: ?string,
     *     verifiedAt: ?\DateTimeImmutable
     * }
     */
    private function normalizePackageManifest(array $manifest, string $packageRoot): array
    {
        $slug = isset($manifest['slug']) && is_string($manifest['slug']) ? trim($manifest['slug']) : '';
        $name = isset($manifest['name']) && is_string($manifest['name']) ? trim($manifest['name']) : '';
        $description = isset($manifest['description']) && is_string($manifest['description']) ? trim($manifest['description']) : '';
        $version = isset($manifest['version']) && is_string($manifest['version']) ? trim($manifest['version']) : '';
        $filesDirectory = isset($manifest['files']) && is_string($manifest['files']) ? trim($manifest['files']) : 'files';

        if ('' === $slug || '' === $name || '' === $description || '' === $version) {
            throw new \RuntimeException('Addon-paketet måste ange slug, name, description och version i addon.json.');
        }

        $normalizedSlug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($slug)) ?? '';
        $normalizedSlug = trim($normalizedSlug, '-');
        if ('' === $normalizedSlug) {
            throw new \RuntimeException('Addon-paketets slug är ogiltig.');
        }

        $filesRoot = $packageRoot.\DIRECTORY_SEPARATOR.$filesDirectory;
        if (!is_dir($filesRoot)) {
            throw new \RuntimeException(sprintf('Addon-paketet saknar filroten "%s".', $filesDirectory));
        }

        $relativeFiles = $this->collectRelativeFiles($filesRoot);
        if ([] === $relativeFiles) {
            throw new \RuntimeException('Addon-paketet innehåller inga filer att packa upp.');
        }

        $hasModulePath = false;
        foreach ($relativeFiles as $relativePath) {
            $normalizedPath = str_replace('\\', '/', $relativePath);
            $allowed = false;
            foreach (self::ALLOWED_TARGET_PREFIXES as $prefix) {
                if (str_starts_with($normalizedPath, $prefix)) {
                    $allowed = true;
                    break;
                }
            }

            if (!$allowed) {
                throw new \RuntimeException(sprintf(
                    'Addon-paketet innehåller filen "%s" utanför tillåtna addon-mappar.',
                    $normalizedPath,
                ));
            }

            if (str_starts_with($normalizedPath, 'src/Module/')) {
                $hasModulePath = true;
            }
        }

        if (!$hasModulePath) {
            throw new \RuntimeException('Addon-paketet måste innehålla kod under src/Module/.');
        }

        $verifiedAt = null;
        if (isset($manifest['verified_at']) && is_string($manifest['verified_at']) && '' !== trim($manifest['verified_at'])) {
            try {
                $verifiedAt = new \DateTimeImmutable(trim($manifest['verified_at']));
            } catch (\Throwable) {
                throw new \RuntimeException('verified_at i addon.json måste vara ett giltigt datum.');
            }
        }

        $adminRoute = isset($manifest['admin_route']) && is_string($manifest['admin_route']) ? trim($manifest['admin_route']) : null;
        if (null !== $adminRoute && '' !== $adminRoute && !str_starts_with($adminRoute, '/')) {
            throw new \RuntimeException('admin_route i addon.json måste vara tom eller börja med /.');
        }

        return [
            'slug' => $normalizedSlug,
            'name' => $name,
            'description' => $description,
            'version' => $version,
            'sourceLabel' => isset($manifest['source_label']) && is_string($manifest['source_label']) && '' !== trim($manifest['source_label'])
                ? trim($manifest['source_label'])
                : 'Zip-import',
            'installStatus' => isset($manifest['install_status']) && is_string($manifest['install_status']) && '' !== trim($manifest['install_status'])
                ? trim($manifest['install_status'])
                : 'configuring',
            'healthStatus' => isset($manifest['health_status']) && is_string($manifest['health_status']) && '' !== trim($manifest['health_status'])
                ? trim($manifest['health_status'])
                : 'unknown',
            'enabled' => isset($manifest['enabled']) ? (bool) $manifest['enabled'] : false,
            'fileCount' => \count($relativeFiles),
            'filesRoot' => $filesRoot,
            'dependencies' => $this->normalizeStringList($manifest['dependencies'] ?? []),
            'environmentVariables' => $this->normalizeStringList($manifest['environment_variables'] ?? []),
            'setupChecklist' => $this->normalizeStringList($manifest['setup_checklist'] ?? []),
            'impactAreas' => $this->normalizeStringList($manifest['impact_areas'] ?? []),
            'adminRoute' => null !== $adminRoute && '' !== $adminRoute ? $adminRoute : null,
            'notes' => isset($manifest['notes']) && is_string($manifest['notes']) && '' !== trim($manifest['notes'])
                ? trim($manifest['notes'])
                : null,
            'verifiedAt' => $verifiedAt,
        ];
    }

    /**
     * @return list<string>
     */
    private function collectRelativeFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), \strlen($directory.\DIRECTORY_SEPARATOR));
            if (false === $relativePath) {
                continue;
            }

            $files[] = str_replace('\\', '/', $relativePath);
        }

        sort($files);

        return $files;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $value,
        ), static fn (string $item): bool => '' !== $item));
    }

    private function copyDirectory(string $sourceDirectory, string $targetDirectory): void
    {
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException('Kunde inte skapa addonets installationsmapp.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), \strlen($sourceDirectory.\DIRECTORY_SEPARATOR));
            if (false === $relativePath) {
                continue;
            }

            $targetPath = $targetDirectory.\DIRECTORY_SEPARATOR.$relativePath;
            if ($item->isDir()) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
                    throw new \RuntimeException('Kunde inte skapa katalog för addon-paketet.');
                }

                continue;
            }

            $parent = dirname($targetPath);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new \RuntimeException('Kunde inte skapa målväg för addonfil.');
            }

            if (!copy($item->getPathname(), $targetPath)) {
                throw new \RuntimeException(sprintf('Kunde inte kopiera addonfilen "%s".', $relativePath));
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());

                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($directory);
    }

    /**
     * @return array{
     *     id: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     sourceLabel: string,
     *     installStatus: string,
     *     healthStatus: string,
     *     enabled: bool,
     *     importedAt: \DateTimeImmutable,
     *     fileCount: int,
     *     installPath: string,
     *     archivePath: string,
     *     filesRoot: string,
     *     dependencies: list<string>,
     *     environmentVariables: list<string>,
     *     setupChecklist: list<string>,
     *     impactAreas: list<string>,
     *     adminRoute: ?string,
     *     notes: ?string,
     *     verifiedAt: ?\DateTimeImmutable
     * }|null
     */
    private function readManifest(string $manifestPath): ?array
    {
        if (!is_file($manifestPath)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $manifest */
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return $this->normalizeStoredManifest($manifest);
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{
     *     id: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     sourceLabel: string,
     *     installStatus: string,
     *     healthStatus: string,
     *     enabled: bool,
     *     importedAt: \DateTimeImmutable,
     *     fileCount: int,
     *     installPath: string,
     *     archivePath: string,
     *     filesRoot: string,
     *     dependencies: list<string>,
     *     environmentVariables: list<string>,
     *     setupChecklist: list<string>,
     *     impactAreas: list<string>,
     *     adminRoute: ?string,
     *     notes: ?string,
     *     verifiedAt: ?\DateTimeImmutable
     * }
     */
    private function normalizeStoredManifest(array $manifest): array
    {
        $importedAt = isset($manifest['importedAt']) && is_string($manifest['importedAt'])
            ? new \DateTimeImmutable($manifest['importedAt'])
            : new \DateTimeImmutable();
        $verifiedAt = isset($manifest['verifiedAt']) && is_string($manifest['verifiedAt']) && '' !== $manifest['verifiedAt']
            ? new \DateTimeImmutable($manifest['verifiedAt'])
            : null;

        return [
            'id' => (string) ($manifest['id'] ?? ''),
            'slug' => (string) ($manifest['slug'] ?? ''),
            'name' => (string) ($manifest['name'] ?? 'Okänt addon'),
            'description' => (string) ($manifest['description'] ?? ''),
            'version' => (string) ($manifest['version'] ?? ''),
            'sourceLabel' => (string) ($manifest['sourceLabel'] ?? 'Zip-import'),
            'installStatus' => (string) ($manifest['installStatus'] ?? 'configuring'),
            'healthStatus' => (string) ($manifest['healthStatus'] ?? 'unknown'),
            'enabled' => (bool) ($manifest['enabled'] ?? false),
            'importedAt' => $importedAt,
            'fileCount' => (int) ($manifest['fileCount'] ?? 0),
            'installPath' => (string) ($manifest['installPath'] ?? ''),
            'archivePath' => (string) ($manifest['archivePath'] ?? ''),
            'filesRoot' => (string) ($manifest['filesRoot'] ?? ''),
            'dependencies' => $this->normalizeStringList($manifest['dependencies'] ?? []),
            'environmentVariables' => $this->normalizeStringList($manifest['environmentVariables'] ?? []),
            'setupChecklist' => $this->normalizeStringList($manifest['setupChecklist'] ?? []),
            'impactAreas' => $this->normalizeStringList($manifest['impactAreas'] ?? []),
            'adminRoute' => isset($manifest['adminRoute']) && is_string($manifest['adminRoute']) && '' !== $manifest['adminRoute']
                ? $manifest['adminRoute']
                : null,
            'notes' => isset($manifest['notes']) && is_string($manifest['notes']) && '' !== trim($manifest['notes'])
                ? trim($manifest['notes'])
                : null,
            'verifiedAt' => $verifiedAt,
            'isActiveVersion' => false,
        ];
    }

    private static function normalizeSlug(string $slug): string
    {
        $normalized = mb_strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }
}
