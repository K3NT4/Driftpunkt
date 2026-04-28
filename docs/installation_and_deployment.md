# Installation och driftsättning

## Lokala förutsättningar

- PHP 8.4 eller senare
- Composer 2
- MariaDB 10.11 eller senare
- PHP-tilläggen `ctype`, `curl`, `iconv`, `intl`, `mbstring`, `pdo`, `pdo_mysql`, `xml` och `zip`
- `DATABASE_URL` mot MariaDB utanför `APP_ENV=test`; vid `mysql://` ska `serverVersion` innehålla `mariadb`
- skrivbar `var/`-katalog för cache, loggar, uppladdningar och delade driftfiler
- webbserverns dokumentrot ska peka på `htdocs/`
- valfritt: Symfony CLI

## Webbrot och `/htdocs`

Driftpunkt använder `htdocs/` som aktiv dokumentrot. En server eller NAS som redan exponerar en mapp som heter `/htdocs` ska därför kunna köra sajten utan att lägga `/public` i URL eller webbserverkonfiguration.

Rekommenderad struktur:

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

Apache/NAS bör peka `DocumentRoot` mot applikationens `htdocs/` när du kan välja webbrot själv. `public/` kan finnas kvar som bakåtkompatibel rot under en övergång, men nya installationer och releasepaket ska verifieras mot `htdocs/`.

På webbhotell där den publika katalogen redan heter `/htdocs` och inte kan ändras kan hela installationspaketet packas upp direkt i den yttre `/htdocs`-mappen:

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

I det läget använder root-`.htaccess` paketets root-`index.php`, blockerar direkta anrop till känsliga mappar som `config/`, `src/`, `vendor/` och `var/`, och serverar `/assets/...` från den interna `htdocs/assets/`. Det kräver att Apache har `mod_rewrite` och tillåter `.htaccess`.

## Lokal installation

1. Installera beroenden:

   ```bash
   composer install
   ```

2. Kontrollera miljö.

   Standardvärdena i `.env` fungerar mot MariaDB i rootens `compose.yaml`. Skapa `.env.local` om du behöver ändra `APP_SECRET`, `DEFAULT_URI`, `DATABASE_URL`, `MAILER_DSN` eller andra lokala värden.

   Exempel på lokal MariaDB-URL:

   ```dotenv
   DATABASE_URL="mysql://driftpunkt:driftpunkt@127.0.0.1:33060/driftpunkt?serverVersion=mariadb-11.8.6&charset=utf8mb4"
   ```

3. Initiera databasen.

   För en helt ny MariaDB-installation:

   ```bash
   php bin/console app:install:fresh
   ```

   Kommandot skapar schema direkt från entiteterna, initierar migrationsmetadata och markerar befintliga migrationer som baseline för den nya installationen.
   Standardkonton för superadmin och admin säkerställs alltid. Standardkonton för tekniker och kund skapas också automatiskt, om du inte anger `--skip-test-accounts`.
   Standardkonton markeras för lösenordsbyte vid första inloggningen.

   Om du i stället arbetar mot en befintlig databas där migrationshistorik redan ska följas:

   ```bash
   php bin/console doctrine:migrations:migrate -n
   ```

4. Skapa konto vid behov:

   ```bash
   php bin/console app:create-test-accounts
   ```

   eller:

   ```bash
   php bin/console app:create-admin dinmail@example.com DittLösenord123 Förnamn Efternamn super_admin
   ```

5. Starta webbserver:

   ```bash
   symfony server:start
   ```

   Om Symfony CLI saknas:

   ```bash
   php -S 127.0.0.1:8000 -t htdocs
   ```

## Docker och lokala hjälptjänster

Repo innehåller `compose.yaml` och `compose.override.yaml` med MariaDB och mailtestning för lokal Docker-baserad utveckling.

Vanliga Docker-kommandon från projektets rotmapp:

```bash
docker compose --env-file .env -f compose.yaml up -d
docker compose --env-file .env -f compose.yaml ps
docker compose --env-file .env -f compose.yaml logs --tail=150 app
```

Om tjänstenamnen avviker mellan miljöer, kontrollera dem med:

```bash
docker compose --env-file .env -f compose.yaml config --services
```

## Produktionsdrift

En fungerande driftsättning bör innehålla:

- webbserver som pekar på `htdocs/`
- alternativt fast webbhotellrot `/htdocs` där paketets root-`.htaccess` är aktiv
- skrivbar `var/`
- skrivbar `htdocs/assets/branding` om logotyp och branding ska kunna ändras från adminytan
- MariaDB-databas med korrekt `serverVersion` i `DATABASE_URL`
- standard-superadmin och minst ett vanligt admin-konto
- process för schemalagda jobb
- worker för köade koduppdateringar om adminytans uppdateringsflöde används
- loggrotation eller motsvarande retention för `var/log/*.log`

Schemalagda jobb bör minst omfatta mailpolling, SLA-kontroll, bilagearkivering och månadsrapporter om rapportmail används. Månadsrapporten kan exempelvis köras efter månadsskifte:

```bash
php bin/console app:reports:send-monthly --env=prod
```

Om adminytans uppdateringsflöde ska användas för att applicera kodpaket måste PHP/webbserver-användaren, normalt `www-data`, också kunna skriva till koddelarna som byts vid uppdatering: `bin/`, `config/`, `htdocs/`, `migrations/`, `public/`, `src/`, `templates/`, `composer.json`, `composer.lock` och `symfony.lock`.

På en mer låst produktion kan kodfilerna i stället ägas av `root` och bara `var/` vara skrivbar för `www-data`, men då ska koduppdateringar göras via SSH/deploy-script i stället för via adminytan.

För en konkret Debian-installation, använd:

- `docs/debian_server_setup.md`
- `deploy/debian/app.env.example`
- `deploy/debian/apache-driftpunkt.conf`
- `deploy/debian/setup.sh`

## Automatisk hantering av köade uppdateringar

Driftpunkt kan lägga koduppdateringar i kö från adminytan. En köad uppdatering sparas som JSON i `var/code_update_runs`. För att uppdateringen inte ska bli liggande som `queued` behöver produktionen ha en schemalagd worker.

Rekommenderad modell:

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

Webbprocessen ska helst inte vara enda mekanismen för att starta själva uppdateringen. Det är säkrare att låta cron, systemd timer eller Docker-scheduler plocka upp jobbet.

### Docker/NAS: cron-worker

Skapa scriptet i projektets rotmapp:

```bash
nano /share/Docker/driftpunkt/run-pending-updates.sh
```

Innehåll:

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

Gör scriptet körbart:

```bash
chmod +x /share/Docker/driftpunkt/run-pending-updates.sh
```

Testa scriptet:

```bash
/share/Docker/driftpunkt/run-pending-updates.sh
```

Om ingen uppdatering väntar ska svaret vara:

```text
Ingen köad Driftpunkt-uppdatering hittades.
```

Lägg in scriptet i root-cron:

```bash
sudo crontab -e
```

Lägg till:

```cron
* * * * * /share/Docker/driftpunkt/run-pending-updates.sh >> /share/Docker/driftpunkt/var/background_jobs/auto_update_cron.log 2>&1
```

Kontrollera:

```bash
sudo crontab -l
tail -n 50 /share/Docker/driftpunkt/var/background_jobs/auto_update_cron.log
```

Om NAS:en inte stödjer vanlig `crontab -e`, skapa motsvarande schemalagd uppgift i NAS:ens webbgränssnitt och kör samma script var 1–5 minut.

### Debian utan Docker: systemd timer

På en vanlig Debian-server utan Docker rekommenderas `systemd timer`. Se `docs/debian_server_setup.md` för komplett exempel. Grundprincipen är:

```bash
sudo systemctl enable --now driftpunkt-update-worker.timer
systemctl status driftpunkt-update-worker.timer
journalctl -u driftpunkt-update-worker.service -n 100 --no-pager
```

## Rekommenderad efter-installation

Verifiera följande efter installation:

- [ ] webbgränssnittet går att nå
- [ ] webbservern pekar på `htdocs/` och `/` visar Driftpunkts startsida
- [ ] om hela paketet ligger direkt i yttre `/htdocs`: kontrollera att `/config/`, `/src/`, `/vendor/` och `/var/` ger 403 eller inte är åtkomliga
- [ ] inloggning fungerar
- [ ] databasanslutning fungerar
- [ ] standard-superadmin och vanligt admin-konto finns
- [ ] `var/` är skrivbar för rätt användare
- [ ] mailservrar och supportinkorgar är konfigurerade vid behov
- [ ] polling, SLA-jobb och bilagearkivering är aktiverade
- [ ] automatisk worker för köade uppdateringar är aktiverad om adminytans uppdateringsflöde används
- [ ] backup och restore har testats
- [ ] driftstatussidan är verifierad

## Uppgradering

Bygg eller leverera uppgraderingspaket:

```bash
php bin/console app:release:build-packages --type=upgrade
```

Uppgraderingspaket innehåller `vendor/` för att NAS- och Debian-installationer inte ska behöva hämta PHP-paket från internet mitt i en uppdatering.

Releasepaketen tar bara med installations- och uppdateringsnära dokumentation från `docs/`: huvudguiden, Debian-guiden, NAS-guiden, mail-/jobbdrift, bilagearkivering, säkerhetskrav, kända driftbegränsningar och addon-releaseguiden. Produktplaner, interna specar, skärmbilder och designunderlag ska inte följa med i installations- eller uppgraderingszipparna.

Rekommenderad ordning i drift:

1. skapa backup
2. aktivera underhållsläge
3. stagea/applicera uppdateringspaket
4. låt uppdateringsflödet köra Composer, MariaDB-migrationer och cache-rensning
5. verifiera att update-worker markerar körningen som `completed`
6. verifiera inloggning, tickets och mail
7. avaktivera underhållsläge

Manuell körning av en specifik update-run:

```bash
php bin/console app:code-update:apply-run RUN_ID --env=prod
```

Docker/NAS:

```bash
docker compose --env-file .env -f compose.yaml exec -T --user www-data app php bin/console app:code-update:apply-run RUN_ID --env=prod
```

Kontrollera status för en körning:

```bash
cat var/code_update_runs/RUN_ID.json
```
