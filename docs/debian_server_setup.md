# Debian server: requirements and installation

This guide covers a conventional Debian installation without Docker. It assumes `/var/www/driftpunkt`, the `www-data` service user and Apache. If Apache uses `mpm-itk` or `AssignUserID`, use the actual virtual-host user instead of `www-data` in commands and ownership settings.

For a new installation, start with [INSTALL.md](../INSTALL.md). Debian 13 is supported with distribution packages and current security updates. `setup.sh --help` lists options; `--check` only performs local preflight checks.

## Requirements

- Debian with shell access and `sudo`.
- PHP 8.4.27 or later within 8.4, or PHP 8.5.12 or later within 8.5.
- Composer 2, Apache 2 with `mod_rewrite`, and MariaDB 10.11 or later.
- A writable `var/` directory for cache, logs, attachments and shared files.
- `PHP_CLI_BINARY=/usr/bin/php`, or the actual server PHP CLI binary.
- Apache DocumentRoot set to `/var/www/driftpunkt/htdocs`.
- systemd timers or cron for background jobs, plus an update worker if web-based updates are used.

For web-based code updates:

- The actual PHP/web-server user must be able to write managed application files: `bin/`, `config/`, `htdocs/`, `migrations/`, `public/`, `src/`, `templates/`, `composer.json`, `composer.lock` and `symfony.lock`.
- Check the virtual host or a protected PHP diagnostic first. On `mpm-itk`/`AssignUserID`, this may be a site-specific user. Use that same user for Composer, migrations and cache warmup.
- If code is deliberately owned by `root`, deploy through SSH/scripts instead and leave only runtime directories writable by the web user.

For an installation allowing web-based updates:

```bash
WEB_USER=www-data
sudo chown -R "$WEB_USER:$WEB_USER" /var/www/driftpunkt
sudo chmod -R u+rwX /var/www/driftpunkt
```

For a more restricted installation:

```bash
sudo chown -R root:www-data /var/www/driftpunkt
sudo chown -R www-data:www-data /var/www/driftpunkt/var /var/www/driftpunkt/htdocs/assets
sudo find /var/www/driftpunkt -type d -exec chmod 755 {} \;
sudo find /var/www/driftpunkt -type f -exec chmod 644 {} \;
sudo chmod 600 /var/www/driftpunkt/.env.local
```

In the restricted model, replace application code using SSH/deployment scripts, not the admin code-update action.

PHP must include the fixes for CVE-2026-109618 and CVE-2026-109619. Composer and application archives do not replace the PHP engine. Patch and verify PHP before applying new application/vendor files. See [PHP updates and verification](installation_and_deployment.md#update-the-php-engine) for package checks, FPM/Apache restarts and separate web/CLI verification.

Required/recommended PHP extensions:

- `gd` with JPEG, PNG and FreeType.
- `fileinfo`, `ctype`, `curl`, `iconv`, `intl`, `mbstring`, `pdo`, `pdo_mysql`, `xml`, `zip`.
- `opcache` is recommended.

MariaDB is the database for Debian, Docker/NAS and live deployments. SQLite is permitted only with `APP_ENV=test`.

<a id="snabb-installation"></a>
## Quick installation

1. Unpack the **installation archive** under `/var/www/driftpunkt` and enter the directory containing `composer.json`. Keep hidden files. Git and private-repository access are not needed to install a release package.

   ```bash
   cd /var/www/driftpunkt
   test -f composer.json && test -f deploy/debian/setup.sh
   ```

2. Run the Debian helper, substituting your own domain:

   ```bash
   sudo env DOMAIN=support.example.com bash deploy/debian/setup.sh
   ```

   It installs Apache, PHP packages, MariaDB, Composer, the virtual host, logrotate and timer files for mail polling and attachment archiving. Timers are not started yet. A missing `.env.local` is created with a random `APP_SECRET` and the configured address. A placeholder database password stops setup before app/web configuration. Configure the database/environment, then repeat the command. Existing secrets are never rotated automatically. For an isolated IP test, use `DOMAIN=YOUR-SERVER-IP PUBLIC_URL=http://YOUR-SERVER-IP`; real customer deployments need HTTPS.

3. Edit the production environment:

   ```bash
   sudo nano /var/www/driftpunkt/.env.local
   ```

   - Preserve the generated `APP_SECRET` and back it up securely.
   - Set `DEFAULT_URI` and `DATABASE_URL` for MariaDB, with `mariadb` in `serverVersion`.
   - Set `PHP_CLI_BINARY` if CLI PHP is not `/usr/bin/php`.
   - Configure `MAILER_DSN` and `MAILER_FROM` when enabling real email.

4. Create the MariaDB database. Replace the example password with a unique secret matching `.env.local`:

   ```bash
   sudo mariadb
   ```

   ```sql
   CREATE DATABASE driftpunkt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'driftpunkt'@'localhost' IDENTIFIED BY 'CHANGE-DATABASE-PASSWORD';
   GRANT ALL PRIVILEGES ON driftpunkt.* TO 'driftpunkt'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

5. Repeat setup after configuration. It uses bundled Composer when available and installs production dependencies from the lock file. Manual equivalent:

   ```bash
   cd /var/www/driftpunkt
   sudo -u www-data php composer install --no-dev --no-interaction --optimize-autoloader
   ```

6. Initialize a completely new database:

   ```bash
   sudo -u www-data php bin/console app:install:fresh --env=prod
   ```

   It creates schema and base data, never user accounts. Create the first super administrator separately; its password is entered without echoing:

   ```bash
   sudo -u www-data php bin/console app:create-admin admin@example.com \
     --first-name=FirstName \
     --last-name=LastName \
     --type=super_admin \
     --env=prod
   ```

   Sign in and open **Settings → Access → MFA policy** (Swedish UI: **Inställningar → Åtkomst → MFA-policy**). Create/import the MFA encryption key, choose available roles and optional mandatory roles, and configure trusted-device duration (14 days by default). No role is automatically forced to use MFA.

7. Set permissions. Apply only to the application tree, not external database volumes:

   ```bash
   sudo chown -R www-data:www-data /var/www/driftpunkt/var /var/www/driftpunkt/htdocs/assets
   sudo find /var/www/driftpunkt -type d -exec chmod 755 {} \;
   sudo find /var/www/driftpunkt -type f -exec chmod 644 {} \;
   sudo chmod 600 /var/www/driftpunkt/.env.local
   ```

8. Validate and reload Apache:

   ```bash
   sudo apache2ctl configtest
   sudo systemctl reload apache2
   ```

   After verifying environment, database and first account, enable scheduled jobs:

   ```bash
   sudo systemctl enable --now driftpunkt-mail-poll.timer driftpunkt-attachment-archive.timer
   ```

9. Enable HTTPS, for example with Certbot:

   ```bash
   sudo apt-get install certbot python3-certbot-apache
   sudo certbot --apache -d driftpunkt.example.com
   ```

10. Enable the update worker if web-based updates will be used; see the section below.

## Important production paths

- Application: `/var/www/driftpunkt`; environment: `.env.local` within that directory.
- Apache virtual host: `/etc/apache2/sites-available/driftpunkt.conf`.
- Active webroot: `htdocs/`; legacy compatible webroot: `public/`.
- Cache/logs/uploads: `var/`; code-update queue: `var/code_update_runs`.
- Log rotation: `/etc/logrotate.d/driftpunkt`.
- Timers: `driftpunkt-mail-poll.timer`, `driftpunkt-attachment-archive.timer`, `driftpunkt-update-worker.timer`.

## Background jobs

The helper installs systemd timers. Inspect them with:

```bash
systemctl list-timers 'driftpunkt-*'
systemctl status driftpunkt-mail-poll.timer
systemctl status driftpunkt-attachment-archive.timer
```

Ready-made timer files live in `deploy/debian/`. Configure SLA checks, monthly reports and update processing as appropriate; production should use explicit scheduling rather than relying only on request-triggered fallback.

Run jobs manually:

```bash
sudo -u www-data php /var/www/driftpunkt/bin/console app:mail:poll --env=prod
sudo -u www-data php /var/www/driftpunkt/bin/console app:internal-reports:retry-notifications --env=prod
sudo -u www-data php /var/www/driftpunkt/bin/console app:archive-ticket-attachments --env=prod
sudo -u www-data php /var/www/driftpunkt/bin/console app:check-ticket-sla --env=prod
```

## Queued updates with a systemd timer

The admin UI stores queued update runs in `var/code_update_runs`. Configure a separate systemd worker so they do not remain `queued`.

Workflow:

```text
Adminyta köar uppdatering
        ↓
systemd timer startar worker
        ↓
workern hittar första queued JSON-fil
        ↓
workern kör app:code-update:apply-run
        ↓
status blir completed eller failed
```

### 1. Create the worker script

```bash
sudo nano /usr/local/bin/driftpunkt-run-pending-updates
```

Contents:

```bash
#!/bin/bash
set -euo pipefail

APP_DIR="/var/www/driftpunkt"
cd "$APP_DIR"

RUN_ID=$(sudo -u www-data php -r '
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
    sudo -u www-data php bin/console app:code-update:apply-run "$RUN_ID" --env=prod
else
    echo "Ingen köad Driftpunkt-uppdatering hittades."
fi
```

Make it executable:

```bash
sudo chmod +x /usr/local/bin/driftpunkt-run-pending-updates
```

Test manually:

```bash
sudo /usr/local/bin/driftpunkt-run-pending-updates
```

### 2. Create the systemd service

```bash
sudo nano /etc/systemd/system/driftpunkt-update-worker.service
```

Contents:

```ini
[Unit]
Description=Driftpunkt pending update worker
After=network.target mariadb.service apache2.service
Wants=mariadb.service

[Service]
Type=oneshot
ExecStart=/usr/local/bin/driftpunkt-run-pending-updates
WorkingDirectory=/var/www/driftpunkt
User=root
Nice=5
```

### 3. Create the systemd timer

```bash
sudo nano /etc/systemd/system/driftpunkt-update-worker.timer
```

Contents:

```ini
[Unit]
Description=Run Driftpunkt pending update worker every minute

[Timer]
OnBootSec=1min
OnUnitActiveSec=1min
Unit=driftpunkt-update-worker.service

[Install]
WantedBy=timers.target
```

### 4. Enable and verify

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now driftpunkt-update-worker.timer
systemctl status driftpunkt-update-worker.timer
```

Read logs:

```bash
journalctl -u driftpunkt-update-worker.service -n 100 --no-pager
```

Start the worker directly when troubleshooting:

```bash
sudo systemctl start driftpunkt-update-worker.service
journalctl -u driftpunkt-update-worker.service -n 50 --no-pager
```

When there is no queued update, expect the following message:

```text
Ingen köad Driftpunkt-uppdatering hittades.
```

## Upgrade

Recommended order when not using the packaged web-update workflow:

```bash
cd /var/www/driftpunkt
sudo -u www-data php bin/console app:maintenance on --message="Uppgradering pågår" --env=prod
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php bin/console doctrine:migrations:migrate -n --env=prod
sudo -u www-data php -d memory_limit=512M bin/console cache:clear --env=prod
sudo -u www-data php bin/console app:maintenance off --env=prod
```

Always back up the database, private environment/keys and runtime files before upgrading. Preserve `APP_SECRET` and separate mail/MFA keys. Never run `app:install:fresh` against existing data.

Current cumulative upgrade archives include `vendor/` and declare their minimum supported version in release metadata. Normally use the latest compatible archive rather than applying every intermediate version. The updater compares installed files, replaces only new/changed files and protects `.env*`, `var/` and uploaded branding.

If using web-based updates, enable `driftpunkt-update-worker.timer` before queuing. Verify that Apache points to `/var/www/driftpunkt/htdocs` before applying an htdocs-based release.

Run a queued update manually:

```bash
cd /var/www/driftpunkt
sudo -u www-data php bin/console app:code-update:apply-run RUN_ID --env=prod
```

Inspect status:

```bash
cat /var/www/driftpunkt/var/code_update_runs/RUN_ID.json
```

## Optional release-directory deployment

For deployments requiring controlled rollback, consider versioned directories and a symlink:

```text
/var/www/driftpunkt/
├── current -> releases/1.0.9
├── releases/
│   ├── 1.0.6
│   └── 1.0.9
└── shared/
    ├── .env.local
    ├── var/
    └── public/uploads/
```

This simplifies changing code versions, but a code-only rollback does not undo database migrations. Restore compatible code and data together.

## Troubleshooting

Check PHP/extensions:

```bash
php -v
php -m
composer check-platform-reqs
```

Read logs:

```bash
sudo journalctl -u apache2 -n 100 --no-pager
sudo tail -n 100 /var/www/driftpunkt/var/log/prod.log
sudo tail -n 100 /var/www/driftpunkt/var/log/mail-poll.log
sudo tail -n 100 /var/www/driftpunkt/var/log/ticket-attachment-archive.log
sudo journalctl -u driftpunkt-mail-poll.service -n 100 --no-pager
sudo journalctl -u driftpunkt-update-worker.service -n 100 --no-pager
```

Inspect timers:

```bash
systemctl list-timers 'driftpunkt-*'
systemctl status driftpunkt-update-worker.timer
```

Common problems:

- `Composer detected issues in your platform`: unsupported PHP or a missing extension.
- HTTP 500 after deployment: inspect `var/log/prod.log`, `.env.local` and database connectivity.
- All routes return 404: wrong webroot or missing `mod_rewrite`/`FallbackResource`.
- No outgoing email: `MAILER_DSN` may still be `null://null`, or SMTP is not configured.
- Internal reports are saved but not delivered centrally: verify the superadmin report settings, an exact match to an active IMAP reply inbox and running mail polling.
- Update remains `queued`: inspect `driftpunkt-update-worker.timer`, start `driftpunkt-update-worker.service` and read its journal.
- `Permission denied`: check code/runtime ownership, particularly when using web-based updates.
