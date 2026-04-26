# Debian-server: krav och installation

Den här guiden är den kortaste vägen till en vanlig Debian-installation utan Docker. Den utgår från att appen ligger i `/var/www/driftpunkt`, körs av `www-data` och exponeras via Apache.

## Krav

Minimikrav:

- Debian-server med shell-access och `sudo`
- PHP 8.4 eller senare
- Composer 2
- Apache 2 med `mod_rewrite`
- MariaDB 10.11 eller senare
- Skrivbar `var/`-mapp för cache, loggar, bilagor och delade filer
- systemd timers eller cron för bakgrundsjobb
- systemd timer för köade koduppdateringar om adminytans uppdateringsflöde används

Krav för webbaserade uppdateringar från adminytan:

- PHP/webbserver-användaren, normalt `www-data`, måste kunna skriva till applikationskoden som uppdateringspaketet hanterar: `bin/`, `config/`, `migrations/`, `public/`, `src/`, `templates/`, `composer.json`, `composer.lock` och `symfony.lock`
- Om servern ska vara hårdare låst med kodfiler ägda av `root` ska adminytans uppdateringsflöde inte användas för kodbyte. Uppdatera då i stället via SSH/deploy-script och låt bara `var/` vara skrivbar för `www-data`.

För en installation där adminytan ska kunna applicera uppdateringspaket:

```bash
sudo chown -R www-data:www-data /var/www/driftpunkt
sudo chmod -R u+rwX /var/www/driftpunkt
```

För en mer låst server:

```bash
sudo chown -R root:www-data /var/www/driftpunkt
sudo chown -R www-data:www-data /var/www/driftpunkt/var /var/www/driftpunkt/public
sudo find /var/www/driftpunkt -type d -exec chmod 755 {} \;
sudo find /var/www/driftpunkt -type f -exec chmod 644 {} \;
```

I den låsta modellen ska koduppdateringar göras via SSH/deploy-script, inte via adminytans kodapplicering.

Rekommenderade PHP-tillägg:

- `ctype`
- `curl`
- `iconv`
- `intl`
- `mbstring`
- `pdo`
- `pdo_mysql`
- `xml`
- `zip`
- `opcache`

Databasval:

- MariaDB är standard för drift, Docker/NAS och Debian.
- SQLite används bara när `APP_ENV=test`; live-, Docker- och Debian-miljöer ska köra MariaDB.

## Snabb installation

1. Kopiera koden till servern:

   ```bash
   sudo mkdir -p /var/www/driftpunkt
   sudo chown "$USER":www-data /var/www/driftpunkt
   git clone <repo-url> /var/www/driftpunkt
   cd /var/www/driftpunkt
   ```

2. Kör Debian-hjälpscriptet:

   ```bash
   DOMAIN=driftpunkt.example.com sudo -E bash deploy/debian/setup.sh
   ```

   Scriptet installerar Apache, PHP-paket, MariaDB, Composer, Apache-vhost, logrotate samt systemd-timers för mailpolling och bilagearkivering. Det skapar också `.env.local` från `deploy/debian/app.env.example` om filen saknas.

3. Redigera produktionsmiljön:

   ```bash
   sudo nano /var/www/driftpunkt/.env.local
   ```

   Byt minst:

   - `APP_SECRET`
   - `DEFAULT_URI`
   - `DATABASE_URL` med MariaDB och `serverVersion` som innehåller `mariadb`
   - `MAILER_DSN` och `MAILER_FROM` när mail ska skickas

4. Skapa MariaDB-databas:

   ```bash
   sudo mariadb
   ```

   ```sql
   CREATE DATABASE driftpunkt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'driftpunkt'@'localhost' IDENTIFIED BY 'byt-databaslösenord';
   GRANT ALL PRIVILEGES ON driftpunkt.* TO 'driftpunkt'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

5. Installera eller uppdatera PHP-beroenden:

   ```bash
   cd /var/www/driftpunkt
   sudo -u www-data composer install --no-dev --optimize-autoloader
   ```

6. Initiera en helt ny databas:

   ```bash
   sudo -u www-data php bin/console app:install:fresh --env=prod
   ```

   Kommandot skapar schema och säkerställer reserv-superadmin samt ett vanligt admin-konto. Standardkonton markeras för lösenordsbyte vid första inloggningen. Vill du inte skapa tekniker- och kundkonton:

   ```bash
   sudo -u www-data php bin/console app:install:fresh --env=prod --skip-test-accounts
   ```

7. Sätt rättigheter:

   ```bash
   sudo chown -R www-data:www-data /var/www/driftpunkt/var /var/www/driftpunkt/public
   sudo find /var/www/driftpunkt -type d -exec chmod 755 {} \;
   sudo find /var/www/driftpunkt -type f -exec chmod 644 {} \;
   ```

8. Ladda om Apache:

   ```bash
   sudo apache2ctl configtest
   sudo systemctl reload apache2
   ```

9. Lägg på HTTPS, till exempel med Certbot:

   ```bash
   sudo apt-get install certbot python3-certbot-apache
   sudo certbot --apache -d driftpunkt.example.com
   ```

10. Aktivera update-worker om adminytans uppdateringsflöde ska användas. Se avsnittet `Automatiska uppdateringar med systemd timer` nedan.

## Viktiga produktionsfiler

- Appkatalog: `/var/www/driftpunkt`
- Miljö: `/var/www/driftpunkt/.env.local`
- Apache-vhost: `/etc/apache2/sites-available/driftpunkt.conf`
- Webbrot: `/var/www/driftpunkt/public`
- Cache/loggar/uploads: `/var/www/driftpunkt/var`
- Loggrotation: `/etc/logrotate.d/driftpunkt`
- Köade koduppdateringar: `/var/www/driftpunkt/var/code_update_runs`
- Mailpolling timer: `driftpunkt-mail-poll.timer`
- Bilagearkivering timer: `driftpunkt-attachment-archive.timer`
- Update-worker timer: `driftpunkt-update-worker.timer`

## Bakgrundsjobb

Systemd-timers installeras av hjälpscriptet. Kontrollera dem med:

```bash
systemctl list-timers 'driftpunkt-*'
systemctl status driftpunkt-mail-poll.timer
systemctl status driftpunkt-attachment-archive.timer
```

Hjälpscriptet installerar de färdiga timerfilerna som finns i `deploy/debian/`. SLA-kontroll, månadsrapporter och update-worker ska antingen hanteras av appens interna fallback eller sättas upp som egna cron/systemd-jobb efter samma mönster.

Köra jobben manuellt:

```bash
sudo -u www-data php /var/www/driftpunkt/bin/console app:mail:poll --env=prod
sudo -u www-data php /var/www/driftpunkt/bin/console app:archive-ticket-attachments --env=prod
sudo -u www-data php /var/www/driftpunkt/bin/console app:check-ticket-sla --env=prod
```

## Automatiska uppdateringar med systemd timer

Adminytans uppdateringsflöde kan skapa en köad update-run i `var/code_update_runs`. För att den inte ska bli liggande som `queued` ska Debian-servern ha en separat worker som körs av systemd.

Flödet är:

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

### 1. Skapa worker-script

```bash
sudo nano /usr/local/bin/driftpunkt-run-pending-updates
```

Innehåll:

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

Gör scriptet körbart:

```bash
sudo chmod +x /usr/local/bin/driftpunkt-run-pending-updates
```

Testa manuellt:

```bash
sudo /usr/local/bin/driftpunkt-run-pending-updates
```

### 2. Skapa systemd-service

```bash
sudo nano /etc/systemd/system/driftpunkt-update-worker.service
```

Innehåll:

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

### 3. Skapa systemd-timer

```bash
sudo nano /etc/systemd/system/driftpunkt-update-worker.timer
```

Innehåll:

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

### 4. Aktivera och kontrollera

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now driftpunkt-update-worker.timer
systemctl status driftpunkt-update-worker.timer
```

Visa loggar:

```bash
journalctl -u driftpunkt-update-worker.service -n 100 --no-pager
```

Kör workern direkt vid felsökning:

```bash
sudo systemctl start driftpunkt-update-worker.service
journalctl -u driftpunkt-update-worker.service -n 50 --no-pager
```

Om ingen uppdatering väntar ska loggen visa:

```text
Ingen köad Driftpunkt-uppdatering hittades.
```

## Uppgradering

Rekommenderad ordning när ett paketerat uppgraderingsflöde inte används:

```bash
cd /var/www/driftpunkt
sudo -u www-data php bin/console app:maintenance on --message="Uppgradering pågår" --env=prod
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php bin/console doctrine:migrations:migrate -n --env=prod
sudo -u www-data php bin/console cache:clear --env=prod
sudo -u www-data php bin/console app:maintenance off --env=prod
```

Ta alltid backup på databas och `var/` innan uppgradering.

Uppgraderingspaket som byggs av Driftpunkt innehåller `vendor/`, så Debian-servern ska kunna uppdateras utan att hämta Composer-paket från internet under själva driftfönstret.

Om adminytans uppdateringsflöde används ska `driftpunkt-update-worker.timer` vara aktiv innan uppdateringen köas. Annars kan körningen bli liggande som `queued` tills den körs manuellt.

Manuell körning av en köad update-run:

```bash
cd /var/www/driftpunkt
sudo -u www-data php bin/console app:code-update:apply-run RUN_ID --env=prod
```

Kontrollera status:

```bash
cat /var/www/driftpunkt/var/code_update_runs/RUN_ID.json
```

## Rekommenderad release-struktur för mer kontrollerad produktion

För kundmiljöer där rollback är viktigt bör Driftpunkt på sikt driftsättas med release-mappar och symlink:

```text
/var/www/driftpunkt/
├── current -> releases/1.0.7
├── releases/
│   ├── 1.0.6
│   └── 1.0.7
└── shared/
    ├── .env.local
    ├── var/
    └── public/uploads/
```

Ett sådant flöde gör det enklare att byta tillbaka till föregående version om en release behöver rullas tillbaka.

## Felsökning

Kontrollera PHP och tillägg:

```bash
php -v
php -m
composer check-platform-reqs
```

Kontrollera loggar:

```bash
sudo journalctl -u apache2 -n 100 --no-pager
sudo tail -n 100 /var/www/driftpunkt/var/log/prod.log
sudo tail -n 100 /var/www/driftpunkt/var/log/mail-poll.log
sudo tail -n 100 /var/www/driftpunkt/var/log/ticket-attachment-archive.log
sudo journalctl -u driftpunkt-mail-poll.service -n 100 --no-pager
sudo journalctl -u driftpunkt-update-worker.service -n 100 --no-pager
```

Kontrollera timers:

```bash
systemctl list-timers 'driftpunkt-*'
systemctl status driftpunkt-update-worker.timer
```

Vanliga fel:

- `Composer detected issues in your platform`: PHP-version eller PHP-tillägg saknas.
- HTTP 500 efter deploy: kontrollera `var/log/prod.log`, `.env.local` och databasanslutningen.
- 404 på alla routes: Apache-vhost pekar inte på `public/` eller `mod_rewrite`/`FallbackResource` saknas.
- Inga mail skickas: `MAILER_DSN` är fortfarande `null://null` eller SMTP-uppgifter saknas.
- Uppdatering ligger kvar som `queued`: kontrollera `driftpunkt-update-worker.timer`, kör `sudo systemctl start driftpunkt-update-worker.service` och läs `journalctl -u driftpunkt-update-worker.service`.
- `Permission denied` vid uppdatering: kontrollera ägarskap på kodfiler och `var/`, särskilt om adminytans uppdateringsflöde används.
