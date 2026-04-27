# Driftpunkt Release Packages

![Driftpunkt](assets/branding/logo-wide.png)

Driftpunkt is a support and operations platform for handling tickets, customer communication, technician work queues, operational status updates, knowledge base content, reports, and maintenance workflows.

This public repository contains packaged Driftpunkt releases only. It does not publish the private application source code, internal modules, environment files, customer data, or deployment secrets.

The screenshots may show the Swedish interface. Language and branding can be changed after installation through the running application and its environment-specific configuration.

## Screenshots

![Driftpunkt homepage](assets/screenshots/homepage.png)

![Customer portal](assets/screenshots/customer-portal.png)

![Technician portal](assets/screenshots/technician-portal.png)

![Admin dashboard](assets/screenshots/admin-dashboard.png)

## What Driftpunkt Includes

- Public pages for status, news, search, contact, and policy information.
- Customer ticket creation and customer-facing case follow-up.
- Technician queues for prioritization, comments, SLA follow-up, and knowledge base work.
- Admin tools for identity, settings, reports, maintenance mode, imports, updates, and operational tasks.
- Release packages with bundled dependencies, metadata, release notes, and SHA-256 checksums.

## Packages

- Fresh installation package: `packages/driftpunkt-install-1.0.9.zip`
- Upgrade packages kept here: up to the latest 3 upgrade builds available during export.
- SHA-256 checksum files are generated beside every package.
- Public README assets exported here: 9.

## What This Repository Contains

- `packages/`: install and upgrade zip files.
- `packages/*.sha256`: checksum files for package verification.
- `assets/`: logo and screenshots used by this README.
- `PUBLIC_EXPORT_MANIFEST.md`: export summary with package versions and checksums.

## Fresh installation

Use the install package for a new server, NAS, or clean application directory.

1. Download the latest `driftpunkt-install-*.zip` file and its matching `.sha256` file from `packages/`.
2. Verify the package before unpacking it:

```bash
cd packages
sha256sum -c driftpunkt-install-1.0.9.zip.sha256
```

3. Create a clean application directory on the target server or NAS.
4. Unpack the zip file into that directory.
5. Configure the environment file for your deployment. Start from the example files included inside the package and set real secrets, database credentials, mail settings, and domain-specific values.
6. Start the database and web runtime for your deployment model.
7. Run the installer from the unpacked application directory:

```bash
php bin/console app:install:fresh --env=prod
```

8. Sign in with the configured administrator account, change default credentials, review branding/language settings, and verify the public status page.

## Upgrade an existing installation

1. Pick the newest `driftpunkt-upgrade-*.zip` that is newer than the installed version. Older upgrade packages are retained so installations can move forward even when they are a few releases behind.
2. Back up the database and application files before applying the upgrade.
3. Verify the checksum before using the package:

```bash
cd packages
sha256sum -c driftpunkt-upgrade-<version>.zip.sha256
```

4. Apply the package through the Driftpunkt admin update flow when available, or unpack it according to your deployment procedure.
5. Run database migrations, cache refresh, service reloads, and any other post-update steps listed in the package metadata and release notes.
6. Verify login, ticket creation, customer/technician portals, status page, and background jobs before leaving maintenance mode.

## Available upgrade packages

- `packages/driftpunkt-upgrade-1.0.9.zip`
- `packages/driftpunkt-upgrade-1.0.8.zip`
- `packages/driftpunkt-upgrade-1.0.7.zip`

## Notes

- Keep private `.env`, backup, database dump, and customer files outside this repository.
- Review `PUBLIC_EXPORT_MANIFEST.md` after every export before committing to the public repository.
- If checksum verification fails, do not install the package. Rebuild and export the release from the private repository.
