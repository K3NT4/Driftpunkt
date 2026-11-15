# NAS installation with Docker Compose

This guide covers the supplied **image-based** Compose stack on a new NAS or in a separate empty project directory. Start with [INSTALL.md](../INSTALL.md). Reading this guide does not change existing containers.

## Requirements

- Docker/Container Manager, Docker Compose v2, an SSH terminal and permission to run Docker.
- The installation archive unpacked into its own directory, including hidden files. Run commands from the package root, not its parent.
- The image build enforces PHP/GD requirements; `manage.sh check` checks runtime prerequisites and preview PNGs. Also verify the active web PHP runtime and application functions.
- Use the generated MariaDB configuration for a new installation. Do not change an existing database image series without a separate migration/backup plan.

## Short fresh-install procedure

Replace the address and administrator email. Behind an HTTPS reverse proxy, pass the public HTTPS address to `init`. HTTP/IP is only for isolated tests.

```bash
bash deploy/nas/manage.sh init http://YOUR-NAS-IP:8080
bash deploy/nas/manage.sh install
bash deploy/nas/manage.sh admin your-email@example.com
```

`init` generates a random `APP_SECRET` and two database passwords in a private environment file (600). The application password is also written into `DATABASE_URL`. Existing environment files are never overwritten. Review `DEFAULT_URI`, `DRIFTPUNKT_PUBLIC_BASE_URL`, `APP_PORT` and `MAILER_FROM`. SMTP remains disabled (`null://null`) until configured.

`install` starts only database and app before initialization. The database must be empty. Explicitly use `install --resume` for an interrupted **new installation**; it does not erase tables and remains protected by the application's existing-table checks. Never use it to upgrade an existing system.

`admin` prompts for a hidden password. Open the site, verify the account and configure **Settings → Access → MFA policy**. Configure real recipients/inboxes before enabling email. Then run:

```bash
bash deploy/nas/manage.sh check
bash deploy/nas/manage.sh start
bash deploy/nas/manage.sh status
```

Business jobs start only with `start`. Use `sudo bash ...` where Docker requires root. The helper does not configure firewall, TLS or reverse proxy settings automatically.

### Persistent storage and existing installations

Database, runtime files and uploaded site branding persist under `deploy/nas/data/{mariadb,var,branding}`. Built-in default logos are seeded without overwriting existing files.

**Before adding the new branding mount to an older stack:** back up and copy its current `/var/www/html/htdocs/assets/branding` into `deploy/nas/data/branding`. An empty bind mount would hide the existing in-container files. Inventory actual volumes before changing old Compose files or database images.

## Build context and package files

Both install and upgrade archives include `.dockerignore` beside `Dockerfile`. Keep it when unpacking/copying, even if a file manager hides dotfiles. Build from the unpacked package root containing both files.

`.dockerignore` excludes:

- `db/`, `var/`, `backup/`, `backups/`, and root database dumps (`*.sql`, `*.sql.gz`, `*.sql.zip`).
- `deploy/nas/data/` and `deploy/nas/backups/`, including database and restored runtime data.
- `public/uploads/`, `htdocs/uploads/` and uploaded `custom-site-*` logos; built-in logos remain included.
- Git metadata, private environment overrides, `deploy/nas/.env`, logs and other listed build artifacts.

Source, `vendor/`, Composer files, web assets and `deploy/php`/`deploy/apache` must remain included. Do not exclude all of `deploy/` or `htdocs/assets/`. Add custom runtime locations to `.dockerignore` too. Package creation also skips NAS data/backup directories without reading them.

The Dockerfile explicitly allows Composer plugins with `COMPOSER_ALLOW_SUPERUSER=1` for the root-run install step only. This lets the allowlisted Symfony Flex plugin run `symfony-cmd` and asset installation. It is not a permanent environment variable in the final container. Composer uses the existing lock file and `--no-dev`, without upgrading dependency versions.

Verify the dotfile and save the build log:

```bash
test -f .dockerignore && test -f Dockerfile
docker build --pull -t driftpunkt:test . 2>&1 | tee /tmp/driftpunkt-docker-build.log
```

The build must finish successfully, with a reasonable transferred code context rather than gigabytes of database/backups. Size varies with dependencies and BuildKit cache. This creates a local test image without replacing running containers. In scripted Bash pipelines, enable `set -o pipefail` so `tee` cannot hide a build failure.

If an older package lacks `.dockerignore`, restore the current file before building. Already built image layers are not changed by an updated ignore file; rebuild from corrected source.

### Historical package-fix verification, 2026-11-11

- Release CLI/Composer commands and public export share `ReleasePackageBuilder`; public export and the GitHub workflow distribute its built archives. Other ZIP writers handle backups/attachments, not release packages.
- Both repository source and an unpacked public install package built with `docker build --pull -t driftpunkt:test .`, exit 0. Production Composer installation, cache clear and Flex `assets:install` succeeded.
- Nine probe files totalling **576 MiB per build root** were placed in excluded runtime directories and a root SQL dump. Filtered contexts and final images excluded all probes while retaining default logos, dependencies and build configuration. Platform checks passed in both images.

| Build source | File bytes after Docker ignore filtering | Reported final transfer |
| --- | ---: | ---: |
| Repository with dev dependencies, tests and internal documents | 90,136,209 bytes (90.14 MB) | 858.26 kB, warm BuildKit cache |
| Unpacked install archive | 47,938,458 bytes (47.94 MB) | 48.80 MB |

Filtered file sizes were measured using a separate local BuildKit `FROM scratch`/`COPY . /` export with the same ignore file. File totals, transferred bytes and archive sizes differ; BuildKit reuses files. These are historical local measurements, not universal size limits.

Affected tests ran in one PHPUnit process:

```bash
php -d memory_limit=128M vendor/bin/phpunit tests/Service/ReleasePackageBuilderTest.php tests/Service/PublicRepoExporterTest.php tests/Service/CodeUpdateManagerTest.php
```

Result: **16 tests / 503 assertions passed**, plus PHP syntax and diff checks. Tests covered `.dockerignore` in both archive types, exclusions, required build files, manifests and NAS data filtering. Local logs under `var/docker-verification/` are not shipped. Application/dependency versions were unchanged by that fix; no NAS deployment was performed in that historical check.

## First super administrator

`app:install:fresh` never creates user accounts. Create the first super administrator separately using the hidden password prompt:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec --user www-data app php bin/console app:create-admin admin@example.com --first-name=FirstName --last-name=LastName --type=super_admin --env=prod
```

After sign-in, open **Settings → Access → MFA policy** to create/import the encryption key and choose availability, optional required roles and trusted-device duration. MFA is not automatically mandatory for any role.

## Background jobs

The `scheduler` service runs:

- `app:mail:poll` each minute.
- `app:internal-reports:retry-notifications` each minute, processing only due failures.
- `app:check-ticket-sla` each minute.
- `app:archive-ticket-attachments` around 02:15.

It does not automatically consume queued code updates. Custom deployments using web code updates need an appropriate NAS/cron worker; see [deployment operations](installation_and_deployment.md).

Read logs:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env logs -f app scheduler
```

## Stability

The Compose stack:

- Restarts containers with `restart: unless-stopped`.
- Waits for database health before starting the application fully.
- Uses an application HTTP healthcheck.
- Sets `var/` and `htdocs/assets` ownership for `www-data` at startup.
- Executes scheduled Symfony commands as `www-data`.

## Backup

Back up database, runtime files, logos and private environment secrets. Archives contain secrets: protect them and keep them outside public directories.

Database dump:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec database mariadb-dump -u root -p driftpunkt > driftpunkt.sql
```

Combined backup under `deploy/nas/backups/`:

```bash
bash deploy/nas/backup.sh
```

The archive contains:

- A MariaDB `--single-transaction` dump with routines, triggers and events.
- Runtime files and available branding data.
- Private environment and Compose configuration under `configuration/`.
- `manifest.txt` with timestamp, Compose file and Git commit when available.

The helper prevents concurrent backups, checks the archive with `tar -tzf` and removes backups older than 14 days. Override retention if needed:

```bash
BACKUP_RETENTION_DAYS=30 bash deploy/nas/backup.sh
```

Restore from an archive:

```bash
CONFIRM=restore bash deploy/nas/restore.sh deploy/nas/backups/driftpunkt-YYYYMMDD-HHMMSS.tar.gz
```

Restoration requires compatible application code and the matching `APP_SECRET`; it is not silently overwritten. Restore stops app/scheduler, imports `database.sql`, restores runtime/branding files and restarts the stack. Existing runtime data is moved to a timestamped `var.before-restore-*` directory. This is a data-changing operation: rehearse it in isolation first. Full restoration was not part of the 2.0.0 release acceptance.

Important directories: `deploy/nas/data/var`, `deploy/nas/data/branding` and `deploy/nas/data/mariadb`.

## Upgrade

### Build stops with `symfony-cmd: not found`

If Composer also reports plugins disabled for root, Flex could not run install scripts. Keep the Dockerfile's install-step `COMPOSER_ALLOW_SUPERUSER=1` and `allow-plugins` in `composer.json`. Do not bypass the problem with `--no-scripts` or ignored platform requirements.

Ignore rules exclude runtime data/backups from the image; they do not delete NAS files or change volume mounts. If context is still several GB, inspect other large project directories before building again.

After obtaining the fix, rebuild and recreate both app and scheduler. A successful image build does not update an already running container's PHP runtime.

Back up first:

```bash
bash deploy/nas/backup.sh
```

### Choose the correct upgrade model

The supplied stack has **application code inside the image**. Web code replacement does not update the scheduler image and can disappear when a container is recreated. Back up, place new code in the correct build root, build a new image, update both services, run migrations/cache steps and verify before resuming business jobs. Never use `manage.sh install` for an upgrade.

Older custom stacks sharing **the entire application directory** may use the controlled superadmin update workflow with backups, maintenance and required post-update steps. Do not mix this with image-based deployment. See the [2.0 upgrade checklist](upgrade_to_2_0_0.md).

PHP must meet the documented 8.4.27/8.5.12 series minimums for the security fixes. App archives and Composer do not replace PHP. Rebuild with `--pull` and recreate both services; plan downtime and keep maintenance active. Stop if the base image cannot be fetched. See [PHP updates and verification](installation_and_deployment.md#update-the-php-engine), including actual web PHP and the temporary AMD64-only fallback where an upstream manifest is missing.

Manual shell upgrade:

```bash
# Lägg först den nya versionens källfiler i denna byggrot; bevara .env och data.
# Pausa verksamhetsjobb och håll underhållsläget aktivt under uppgraderingen.
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env build --pull app &&
  docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env up -d --force-recreate app scheduler
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec -T app php -v
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec -T scheduler php -v
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec -T app composer check-platform-reqs --no-dev
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec --user www-data app php bin/console doctrine:migrations:migrate -n --env=prod
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec --user www-data app php -d memory_limit=512M bin/console cache:clear --env=prod
```
