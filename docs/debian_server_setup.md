# Debian-server: krav och installation

Den här guiden är den kortaste vägen till en vanlig Debian-installation utan Docker. Den utgår från att appen ligger i `/var/www/driftpunkt`, körs av `www-data` och exponeras via Apache.

## Krav

Minimikrav:

- Debian-server med shell-access och `sudo`
- PHP 8.4 eller senare
- Composer
- Apache 2 med `mod_rewrite`
- Databas: MariaDB
- Skrivbar `var/`-mapp för cache, loggar, bilagor och delade filer
- Systemd timers eller cron för bakgrundsjobb

Krav för webbaserade uppdateringar från adminytan:

- PHP/webbserver-användaren, normalt `www-data`, måste kunna skriva till applikationskoden som uppdateringspaketet hanterar: `bin/`, `config/`, `migrations/`, `public/`, `src/`, `templates/`, `composer.json`, `composer.lock` och `symfony.lock`
- Om servern ska vara hårdare låst med kodfiler ägda av `root` ska adminytans uppdateringsflöde inte användas för kodbyte. Uppdatera då i stället via SSH/deploy-script och låt bara `var/` vara skrivbar för `www-data`.

För en installation där adminytan ska kunna applicera uppdateringspaket:

```bash
sudo chown -R www-data:www-data /var/www/driftpunkt
sudo chmod -R u+rwX /var/www/driftpunkt
```

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
- SQLite används bara för test/lokal legacy-körning.

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

   Scriptet installerar Apache, PHP-paket, MariaDB, Composer, Apache-vhost och systemd-timers. Det skapar också `.env.local` från `deploy/debian/app.env.example` om filen saknas.

3. Redigera produktionsmiljön:

   ```bash
   sudo nano /var/www/driftpunkt/.env.local
   ```

   Byt minst:

   - `APP_SECRET`
   - `DEFAULT_URI`
   - `DATABASE_URL`
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

9. Lagga på HTTPS, till exempel med Certbot:

   ```bash
   sudo apt-get install certbot python3-certbot-apache
   sudo certbot --apache -d driftpunkt.example.com
   ```

## Viktiga produktionsfiler

- Appkatalog: `/var/www/driftpunkt`
- Miljö: `/var/www/driftpunkt/.env.local`
- Apache-vhost: `/etc/apache2/sites-available/driftpunkt.conf`
- Webbrot: `/var/www/driftpunkt/public`
- Cache/loggar/uploads: `/var/www/driftpunkt/var`
- Mailpolling timer: `driftpunkt-mail-poll.timer`
- Bilagearkivering timer: `driftpunkt-attachment-archive.timer`

## Bakgrundsjobb

Systemd-timers installeras av hjälpscriptet. Kontrollera dem med:

```bash
systemctl list-timers 'driftpunkt-*'
systemctl status driftpunkt-mail-poll.timer
systemctl status driftpunkt-attachment-archive.timer
```

Köra jobben manuellt:

```bash
sudo -u www-data php /var/www/driftpunkt/bin/console app:mail:poll --env=prod
sudo -u www-data php /var/www/driftpunkt/bin/console app:archive-ticket-attachments --env=prod
sudo -u www-data php /var/www/driftpunkt/bin/console app:check-ticket-sla --env=prod
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
sudo journalctl -u driftpunkt-mail-poll.service -n 100 --no-pager
```

Vanliga fel:

- `Composer detected issues in your platform`: PHP-version eller PHP-tillägg saknas.
- HTTP 500 efter deploy: kontrollera `var/log/prod.log`, `.env.local` och databasanslutningen.
- 404 på alla routes: Apache-vhost pekar inte på `public/` eller `mod_rewrite`/`FallbackResource` saknas.
- Inga mail skickas: `MAILER_DSN` är fortfarande `null://null` eller SMTP-uppgifter saknas.
