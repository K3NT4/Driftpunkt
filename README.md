# Driftpunkt Release Packages

This public repository contains packaged Driftpunkt releases only. It does not publish the private application source code, internal modules, or deployment tooling that is not required for installation packages.

## Packages

- Fresh installation package: `packages/driftpunkt-install-1.0.9.zip`
- Upgrade packages kept here: up to the latest 3 upgrade builds available during export.
- SHA-256 checksum files are generated beside every package.

## Fresh installation

1. Download the latest `driftpunkt-install-*.zip` file and its matching `.sha256` file from `packages/`.
2. Verify the package before unpacking it:

```bash
cd packages
sha256sum -c driftpunkt-install-1.0.9.zip.sha256
```

3. Unpack the zip file on the target server or NAS.
4. Configure the environment file for your deployment. Start from the example files included inside the package and set real secrets, database credentials, mail settings, and domain-specific values.
5. Start the database and web runtime, then run the installer from the unpacked application directory:

```bash
php bin/console app:install:fresh --env=prod
```

## Upgrade an existing installation

1. Pick the newest `driftpunkt-upgrade-*.zip` that is newer than the installed version. Older upgrade packages are retained so installations can move forward even when they are a few releases behind.
2. Back up the database and application files before applying the upgrade.
3. Verify the checksum before using the package:

```bash
cd packages
sha256sum -c driftpunkt-upgrade-<version>.zip.sha256
```

4. Apply the package through the Driftpunkt admin update flow when available, or unpack it according to your deployment procedure.
5. Run the post-update checks listed in the package metadata and release notes.

## Available upgrade packages

- `packages/driftpunkt-upgrade-1.0.9.zip`
- `packages/driftpunkt-upgrade-1.0.8.zip`
- `packages/driftpunkt-upgrade-1.0.7.zip`

## Notes

- Keep private `.env`, backup, database dump, and customer files outside this repository.
- Review `PUBLIC_EXPORT_MANIFEST.md` after every export before committing to the public repository.
- If checksum verification fails, do not install the package. Rebuild and export the release from the private repository.
