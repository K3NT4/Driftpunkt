# Driftpunkt Release Packages

![Driftpunkt](assets/branding/logo-wide.png)

Driftpunkt is a support and operations platform for handling tickets, customer communication, technician work queues, operational status updates, knowledge base content, reports, company administration, and maintenance workflows.

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
- Admin tools for identity, paginated company management, settings, reports, maintenance mode, imports, updates, and operational tasks.
- Company hierarchy management where subsidiaries stay grouped with their parent company and search opens relevant groups.
- Mail ingestion, draft review, company monthly reports, public ticket intake, read-only ticket API, and package-based updates.
- Release packages with bundled dependencies, metadata, release notes, and SHA-256 checksums.

## Packages

- Current exported release: `1.0.30`.
- Fresh installation package: `packages/driftpunkt-install-1.0.30.zip`
- Upgrade packages kept here: up to the latest 3 upgrade builds available during export.
- SHA-256 checksum files are generated beside every package.
- Public README assets exported here: 9.

## Latest Release Notes

These notes are copied from the packaged release metadata for the current exported version.

### Driftpunkt 1.0.30

### Fixat och förbättrat
- Arkiverade/borttagna användare visas nu i en egen grupp, `Borttagna användare`, i identitetsadmin.
- Anonymiserade admin- och teknikerkonton ligger inte längre kvar under Administratörer eller Tekniker efter radering.

### Säkerhet och behörighet
- När ett konto måste arkiveras för historikens skull tas admin-, superadmin- och teknikerbehörighet bort.
- Arkiverade konton får en icke-privilegierad kontotyp och tomma extra roller, så de inte kan råka återaktiveras med gammal adminbehörighet.
- Befintlig historik, kommentarer och ärendelänkar bevaras fortsatt för spårbarhet.

### Databas och drift
- Nya migrationer: nej.
- Kräver cache-refresh: ja.
- Kräver omstart/reload: rekommenderas efter uppdatering så PHP/OPcache och Apache laddar ny kod.

### Kontroll efter uppdatering
- Ta bort ett testkonto med historik som admin eller tekniker och kontrollera att det hamnar under `Borttagna användare`.
- Kontrollera att det anonymiserade kontot inte längre visar Admin, Super Admin eller Tekniker som typ.
- Kontrollera att vanliga aktiva/inaktiva admins fortsatt ligger under Administratörer.

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
sha256sum -c driftpunkt-install-1.0.30.zip.sha256
```

3. Create a clean application directory on the target server or NAS.
4. Unpack the zip file into that directory.
5. Point the web server document root to the unpacked application directory's `htdocs/` folder. On shared hosting where the fixed public directory is already named `/htdocs`, unpack the whole package there and keep the package root `.htaccess` enabled.
6. Configure the environment file for your deployment. Start from the example files included inside the package and set real secrets, database credentials, mail settings, and domain-specific values.
7. Start the database and web runtime for your deployment model.
8. Run the installer from the unpacked application directory:

```bash
php bin/console app:install:fresh --env=prod
```

9. Sign in with the configured administrator account, change default credentials, review branding/language settings, and verify the public status page.

## Install on a new Debian server

This flow installs Driftpunkt without Docker on a clean Debian server. Replace `driftpunkt.example.com`, passwords, and mail settings before production use.

1. Install the tools needed to unpack the release package:

```bash
sudo apt-get update
sudo apt-get install -y unzip
```

2. Download or copy `driftpunkt-install-1.0.30.zip` and `driftpunkt-install-1.0.30.zip.sha256` to the server, then verify the package:

```bash
sha256sum -c driftpunkt-install-1.0.30.zip.sha256
```

3. Unpack the release into `/var/www/driftpunkt`:

```bash
rm -rf /tmp/driftpunkt-install
mkdir -p /tmp/driftpunkt-install
unzip driftpunkt-install-1.0.30.zip -d /tmp/driftpunkt-install
sudo mkdir -p /var/www/driftpunkt
sudo cp -a /tmp/driftpunkt-install/driftpunkt-install-1.0.30/. /var/www/driftpunkt/
cd /var/www/driftpunkt
```

4. Run the Debian setup script. It installs Apache, PHP packages, MariaDB, Composer, logrotate, and Driftpunkt systemd timers:

```bash
DOMAIN=driftpunkt.example.com sudo -E bash deploy/debian/setup.sh
```

5. Edit `/var/www/driftpunkt/.env.local` and set real values for `APP_SECRET`, `DEFAULT_URI`, `DATABASE_URL`, `MAILER_DSN`, and `MAILER_FROM`:

```bash
sudo nano /var/www/driftpunkt/.env.local
```

6. Create the MariaDB database and user. Use the same password in `DATABASE_URL`:

```bash
sudo mariadb
```

```sql
CREATE DATABASE driftpunkt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'driftpunkt'@'localhost' IDENTIFIED BY 'change-this-database-password';
GRANT ALL PRIVILEGES ON driftpunkt.* TO 'driftpunkt'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

7. Initialize Driftpunkt:

```bash
sudo -u www-data php /var/www/driftpunkt/bin/console app:install:fresh --env=prod
```

8. Confirm that Apache serves `/var/www/driftpunkt/htdocs`, then reload Apache and add HTTPS, for example with Certbot:

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
sudo apt-get install -y certbot python3-certbot-apache
sudo certbot --apache -d driftpunkt.example.com
```

## Install on a new NAS with Docker Compose

This flow uses the Docker Compose stack included inside the install package. Adjust `/volume1/docker/driftpunkt` to the application path used by your NAS.

1. Copy `driftpunkt-install-1.0.30.zip` and `driftpunkt-install-1.0.30.zip.sha256` to the NAS, then verify the package:

```bash
sha256sum -c driftpunkt-install-1.0.30.zip.sha256
```

2. Unpack the release into a persistent NAS folder:

```bash
rm -rf /tmp/driftpunkt-install
mkdir -p /tmp/driftpunkt-install /volume1/docker/driftpunkt
unzip driftpunkt-install-1.0.30.zip -d /tmp/driftpunkt-install
cp -a /tmp/driftpunkt-install/driftpunkt-install-1.0.30/. /volume1/docker/driftpunkt/
cd /volume1/docker/driftpunkt
```

3. Create and edit the NAS environment file:

```bash
cp deploy/nas/.env.example deploy/nas/.env
nano deploy/nas/.env
```

Set real values for `APP_SECRET`, `DEFAULT_URI`, `MARIADB_PASSWORD`, `MARIADB_ROOT_PASSWORD`, `DATABASE_URL`, `MAILER_DSN`, and `MAILER_FROM`.

4. Create persistent data folders and start the stack:

```bash
mkdir -p deploy/nas/data/var deploy/nas/data/mariadb
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env up -d --build
```

5. Initialize Driftpunkt inside the app container:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec --user www-data app php bin/console app:install:fresh --env=prod
```

6. Open the URL from `DEFAULT_URI`, sign in, change default credentials, and confirm that the app and scheduler containers are healthy:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env ps
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env logs -f app scheduler
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
5. Confirm that the web server document root points to `htdocs/`, or that the package root `.htaccess` is active when the whole package lives in a fixed `/htdocs` directory.
6. Run database migrations, cache refresh, service reloads, and any other post-update steps listed in the package metadata and release notes.
7. For version 1.0.21 or later, also verify Admin -> Identity links to the dedicated company page and Admin -> Companies paginates company groups correctly.
8. Verify login, ticket creation, customer/technician portals, status page, and background jobs before leaving maintenance mode.

## Available upgrade packages

- `packages/driftpunkt-upgrade-1.0.30.zip`
- `packages/driftpunkt-upgrade-1.0.29.zip`
- `packages/driftpunkt-upgrade-1.0.28.zip`

## Notes

- Keep private `.env`, backup, database dump, and customer files outside this repository.
- Driftpunkt release packages prefer `htdocs/` as document root. If the full package must live directly in a fixed `/htdocs` web root, verify that `.htaccess` blocks `config/`, `src/`, `vendor/`, and `var/` before production use.
- Review `PUBLIC_EXPORT_MANIFEST.md` after every export before committing to the public repository.
- If checksum verification fails, do not install the package. Rebuild and export the release from the private repository.
