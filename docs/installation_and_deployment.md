# Installation and deployment

For a new release-package installation, start with [INSTALL.md](../INSTALL.md). Never initialize a fresh database over existing data.

For an existing installation, use the [2.0.1 upgrade checklist](upgrade_to_2_0_1.md). Back up `var/declarative_addons/` with uploads, private environment files and encryption keys. See [custom addons](addons.md) for the supported JSON format; executable ZIP addons and SupportDesk are retired.

## Prerequisites

- PHP 8.4.27 or later within 8.4, or PHP 8.5.12 or later within 8.5; Composer 2; MariaDB 10.11 or later.
- Extensions: `ctype`, `curl`, `fileinfo`, `gd` with JPEG/PNG/FreeType, `iconv`, `intl`, `mbstring`, `pdo`, `pdo_mysql`, `xml`, `zip`.
- A MariaDB `DATABASE_URL` outside `APP_ENV=test`; with `mysql://`, `serverVersion` must contain `mariadb`.
- `PHP_CLI_BINARY` set to the actual CLI binary, commonly `/usr/bin/php`, for jobs started from Apache/mod_php.
- Writable `var/` for cache, logs, uploads and shared operational files.
- Web document root set to `htdocs/`; Symfony CLI is optional for development.

## Webroot and htdocs

The active document root is `htdocs/`. A server/NAS already exposing a directory named `/htdocs` does not need `/public` in its URL.

Recommended structure:

```text
/var/www/driftpunkt/
├── bin/
├── config/
├── htdocs/
│   ├── .htaccess
│   ├── index.php
│   └── assets/
├── src/
├── templates/
├── var/
└── vendor/
```

Where configurable, point Apache/NAS DocumentRoot to the application's `htdocs/`. The legacy `public/` root remains compatible during transition, but verify new deployments against `htdocs/`.

On hosting with a fixed external `/htdocs` directory, the whole installation package can be unpacked directly there:

```text
/htdocs/
├── .htaccess
├── index.php
├── bin/
├── config/
├── htdocs/
│   └── assets/
├── src/
├── templates/
├── var/
└── vendor/
```

Root `.htaccess` then uses root `index.php`, blocks sensitive directories such as `config/`, `src/`, `vendor/` and `var/`, and serves `/assets/...` from internal `htdocs/assets/`. Apache must enable `mod_rewrite` and permit `.htaccess`.

## Local installation

1. Install dependencies:

   ```bash
   composer install
   ```

2. Configure the environment.

   Repository `.env` defaults target MariaDB in root `compose.yaml`; override installation-specific values in `.env.local`. Set a unique `APP_SECRET`, `DEFAULT_URI`, `DATABASE_URL` and mail settings. Google Workspace OAuth is optional and can be configured by superadmin under **Email & inboxes**; unused Google integration needs no additional variables.
   Leave `DRIFTPUNKT_ENABLE_DEMO_DATA=0` in production. Only set a strong random `DRIFTPUNKT_API_TOKEN` if enabling the read-only `/api/v1/tickets` API.
   For central internal-report delivery, configure recipient and reply inbox in the superadmin cockpit. The reply address must exactly match an active IMAP support inbox; mail polling must run to display email replies in report threads.

   Local database example:

   ```dotenv
   DATABASE_URL="mysql://driftpunkt:driftpunkt@127.0.0.1:33060/driftpunkt?serverVersion=mariadb-11.8.6&charset=utf8mb4"
   ```

3. Initialize only a completely new database:

   ```bash
   php bin/console app:install:fresh --env=prod
   ```

   It creates schema/base data, initializes migration metadata and marks existing migrations as the new installation's baseline. It never creates accounts. Create the first super administrator separately with hidden password input:

   ```bash
   php bin/console app:create-admin admin@example.com \
     --first-name=FirstName \
     --last-name=LastName \
     --type=super_admin \
     --env=prod
   ```

   Open **Settings → Access → MFA policy** after sign-in. Create/import the encryption key, choose eligible roles, optionally require MFA for selected roles, and choose trusted-device duration (14 days by default). Installation/upgrading does not automatically require MFA for any role.

   For an existing database with migration history, use migrations instead:

   ```bash
   php bin/console doctrine:migrations:migrate -n
   ```

4. Test accounts may be created only in development/test:

   ```bash
   php bin/console app:create-test-accounts
   ```

   Or create an explicit administrator:

   ```bash
   php bin/console app:create-admin your-email@example.com --first-name=FirstName --last-name=LastName --type=super_admin
   ```

5. Start a development server:

   ```bash
   symfony server:start
   ```

   Without Symfony CLI:

   ```bash
   php -S 127.0.0.1:8000 -t htdocs
   ```

## Docker and local helper services

Root `compose.yaml` and `compose.override.yaml` provide MariaDB and mail testing for local Docker development.

Typical commands from the project root:

```bash
docker compose --env-file .env -f compose.yaml up -d
docker compose --env-file .env -f compose.yaml ps
docker compose --env-file .env -f compose.yaml logs --tail=150 app
```

If service names differ, inspect them first:

```bash
docker compose --env-file .env -f compose.yaml config --services
```

<a id="säkerhetsuppdatera-php-motorn"></a>
## Update the PHP engine

PHP 8.4.27 and 8.5.12 address CVE-2026-109618 and CVE-2026-109619, which may allow execution before application code runs. See the [official PHP security notice](https://www.php.net/archive/2026.php#2026-10-21-5). Supported patch levels are checked per series: PHP 8.5.11 does not meet the requirement simply because it is newer than 8.4.27.

Composer/application updates replace PHP libraries, not the PHP engine. Patch and verify web and background runtimes **before applying new application/vendor files**. Docker dependencies are installed in the updated base image. Back up and plan downtime for process/container restarts.

### Debian/Ubuntu

Inventory installed PHP packages and candidates in Bash. This selects only installed 8.4/8.5 packages, including an Apache module if present:

```bash
mapfile -t driftpunkt_php_packages < <(
  dpkg-query -W -f='${binary:Package}\t${db:Status-Abbrev}\n' \
    'php8.4*' 'php8.5*' 'libapache2-mod-php8.4' 'libapache2-mod-php8.5' 2>/dev/null \
    | awk '$2 == "ii" {print $1}'
)
printf '%s\n' "${driftpunkt_php_packages[@]}"
sudo apt-get update
if ((${#driftpunkt_php_packages[@]})); then
  apt-cache policy "${driftpunkt_php_packages[@]}"
  apt-get -s install --only-upgrade "${driftpunkt_php_packages[@]}"
fi
```

Verify corrected candidates before proceeding. If none exist, update through the packaging/provider actually used. A distribution backport may address vulnerabilities, but Driftpunkt's version check still requires the documented patch levels.

The next command upgrades installed PHP packages and may restart services. Use the same Bash session after reviewing the simulation:

```bash
if ((${#driftpunkt_php_packages[@]})); then
  sudo apt-get install --only-upgrade "${driftpunkt_php_packages[@]}"
fi
```

Restart the site's actual PHP service: for example `php8.4-fpm`, `php8.5-fpm` or Apache for mod_php. Restart long-running workers too. CachyOS/Arch instead needs a coordinated `sudo pacman -Syu`, planned separately because it may update more than PHP.

### Docker/NAS

The Dockerfile uses PHP 8.4.27. Existing containers/cached images may still use an older engine. From the project root with NAS configuration:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env build --pull app &&
  docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env up -d --force-recreate app scheduler
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec -T app php -v
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec -T scheduler php -v
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec -T app composer check-platform-reqs --no-dev
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env ps
```

Building fetches the base image; recreation restarts both services with the new image. Both version checks must show the expected PHP version and services should be running, with app healthy. Adapt paths to the actual deployment.

If pulling fails, stop; do not fall back to an older PHP tag or stale application image. On 2026-10-21, the regular upstream multiarchitecture index lacked platform manifests. The official architecture-specific image provided the following temporary **AMD64-only** alternative:

```bash
if [ "$(docker version --format '{{.Server.Arch}}')" = amd64 ]; then
  docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env build --pull \
    --build-arg PHP_BASE_IMAGE=docker.io/amd64/php:8.4.27-apache app &&
    docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env up -d --force-recreate app scheduler
else
  printf '%s\n' 'Avbryter: denna tillfälliga image kräver AMD64.' >&2
  false
fi
```

Repeat version, platform and web checks afterward. ARM needs a corrected image for its architecture. The normal project choice remains multiarchitecture when the upstream index is complete.

### Verify CLI and actual web PHP

```bash
php -r 'printf("PHP %s (%s)\n", PHP_VERSION, PHP_SAPI);'
composer check-platform-reqs --no-dev
```

CLI must satisfy the supported series/patch requirement and Composer must pass platform checks. Check even when `vendor/` is bundled: Composer's minimum-version autoload check does not fully enforce the separate 8.5 minimum. Verify `PHP_CLI_BINARY` and paths used by cron/systemd.

`php -v` checks CLI only. The site's FPM pool or Apache module may differ. Verify PHP version/SAPI through a protected hosting diagnostic or a temporary localhost-restricted request exposing only `PHP_VERSION` and `PHP_SAPI`. Remove it afterward; never publish `phpinfo()`. For Docker, check after recreating app. Verify homepage, login, tickets and jobs before disabling maintenance.

## Production deployment

A complete deployment should have:

- DocumentRoot `htdocs/`, or the fixed external `/htdocs` arrangement with root `.htaccess`.
- Correct writable runtime directories and `PHP_CLI_BINARY`.
- Writable `htdocs/assets/branding` if administrators upload branding. Preserve uploaded `custom-site-*` files during code updates.
- MariaDB with correct `serverVersion` in `DATABASE_URL`.
- An explicitly created super administrator and suitable ordinary admin accounts.
- Scheduling for mail polling, SLA, attachment archiving and monthly reports when used.
- A queued-update worker if web updates are enabled, plus log rotation/retention.

Monthly reports can run after a month boundary:

```bash
php bin/console app:reports:send-monthly --env=prod
```

Web updates require write permission to the managed code files listed in the Debian guide. Check the actual service user, particularly on `mpm-itk`/`AssignUserID`, and run Composer/migrations/cache tasks as that user. A root-owned-code deployment should use SSH/scripts for updates instead of granting web code-write access.

For Debian, see `docs/debian_server_setup.md`, `deploy/debian/app.env.example`, `deploy/debian/apache-driftpunkt.conf` and `deploy/debian/setup.sh`.

## Processing queued updates

Admin code updates create JSON runs under `var/code_update_runs`. Configure an external worker so runs do not remain `queued`.

Recommended model:

```text
Adminyta köar uppdatering
        ↓
cron/systemd timer kör worker
        ↓
worker hittar queued update
        ↓
worker kör app:code-update:apply-run
        ↓
status blir completed eller failed
```

The web request should not be the only reliable update launcher. Use an appropriate cron/systemd worker. The stock image-based NAS deployment uses image replacement instead of web code replacement.

### Docker/NAS: cron worker for compatible custom stacks

Create the script in the project root:

```bash
nano /share/Docker/driftpunkt/run-pending-updates.sh
```

Contents:

```sh
#!/bin/sh

cd /share/Docker/driftpunkt || exit 1

RUN_ID=$(docker compose --env-file .env -f compose.yaml exec -T app php -r '
$dir = "var/code_update_runs";
if (!is_dir($dir)) { exit; }

$files = glob($dir . "/*.json");
rsort($files);

foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);
    if (($data["status"] ?? "") === "queued") {
        echo basename($file, ".json");
        exit;
    }
}
')

if [ -n "$RUN_ID" ]; then
    echo "Kör Driftpunkt-uppdatering: $RUN_ID"
    docker compose --env-file .env -f compose.yaml exec -T --user www-data app php bin/console app:code-update:apply-run "$RUN_ID" --env=prod
else
    echo "Ingen köad Driftpunkt-uppdatering hittades."
fi
```

Make it executable:

```bash
chmod +x /share/Docker/driftpunkt/run-pending-updates.sh
```

Test it:

```bash
/share/Docker/driftpunkt/run-pending-updates.sh
```

With no queued update, expect:

```text
Ingen köad Driftpunkt-uppdatering hittades.
```

Edit root's crontab:

```bash
sudo crontab -e
```

Add:

```cron
* * * * * /share/Docker/driftpunkt/run-pending-updates.sh >> /share/Docker/driftpunkt/var/background_jobs/auto_update_cron.log 2>&1
```

Verify:

```bash
sudo crontab -l
tail -n 50 /share/Docker/driftpunkt/var/background_jobs/auto_update_cron.log
```

If normal crontab editing is unavailable, configure an equivalent NAS scheduled task every 1–5 minutes.

### Debian without Docker: systemd timer

Use the complete example in the [Debian guide](debian_server_setup.md). Basic steps:

```bash
sudo systemctl enable --now driftpunkt-update-worker.timer
systemctl status driftpunkt-update-worker.timer
journalctl -u driftpunkt-update-worker.service -n 100 --no-pager
```

## Post-installation checklist

- [ ] Web UI and homepage are reachable through the correct webroot.
- [ ] Sensitive directories (`config/`, `src/`, `vendor/`, `var/`) cannot be requested directly, particularly on fixed `/htdocs` hosting.
- [ ] Sign-in, database connectivity and explicit administrator accounts work.
- [ ] Runtime directories are writable by the correct user.
- [ ] Required mail servers/inboxes are configured and actual mail flow is verified before enabling it.
- [ ] Polling, SLA and attachment jobs are enabled where needed.
- [ ] Update processing is configured for the selected deployment model.
- [ ] Backup/restoration and HTTPS are verified in the intended environment.
- [ ] Public status works as intended.

## Upgrade

Build or distribute upgrade archives:

```bash
php bin/console app:release:build-packages --type=upgrade
```

Current upgrade archives are cumulative and include production `vendor/`. Check their minimum supported version and normally use the latest compatible archive. The updater compares code, replaces changed/new files, removes obsolete managed files and protects `.env*`, `var/` and uploaded branding.

Packages include selected installation/operations documentation, not internal plans, design sources or screenshots. Preserve existing mail accounts, environment files and encryption keys; do not initialize a new database during an upgrade.

Docker/cache CLI warmup uses a separate 512 MB budget. Composer post-update steps default to `COMPOSER_MEMORY_LIMIT=512M` unless explicitly configured. This does not change web PHP memory limits.

Recommended operational order:

1. Create coordinated backups.
2. Enable maintenance and pause mail intake/business jobs.
3. Patch and verify PHP for web and workers. Maintenance alone does not protect against engine vulnerabilities; isolate public access if needed.
4. Stage/apply the compatible archive, or replace both images for image-based deployment.
5. Complete locked Composer installation, MariaDB migrations and cache warmup.
6. Verify the run is `completed` and health confirmation is pending.
7. Verify sign-in, tickets, permissions and configured mail flow.
8. Disable maintenance/resume external access only after checks pass.

Run a particular queued update:

```bash
php bin/console app:code-update:apply-run RUN_ID --env=prod
```

Docker/NAS:

```bash
docker compose --env-file .env -f compose.yaml exec -T --user www-data app php bin/console app:code-update:apply-run RUN_ID --env=prod
```

Inspect a run:

```bash
cat var/code_update_runs/RUN_ID.json
```

See the [2.0 upgrade checklist](upgrade_to_2_0_0.md) for preserved mail settings, support-card requirements, layout choices and recovery from an older cache/container update failure.
