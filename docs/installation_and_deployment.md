# Installation och driftsättning

Ny installation från paket: börja med [INSTALL.md](../INSTALL.md) för korta steg till Debian 13 eller NAS. Använd inte nyinstallationskommandot på befintliga data.

## Lokala förutsättningar

- PHP 8.4.27 eller senare inom 8.4-serien, alternativt PHP 8.5.12 eller senare inom 8.5-serien
- Composer 2
- MariaDB 10.11 eller senare
- PHP-tilläggen `ctype`, `curl`, `fileinfo`, `gd` (JPEG/PNG/FreeType), `iconv`, `intl`, `mbstring`, `pdo`, `pdo_mysql`, `xml` och `zip`
- `DATABASE_URL` mot MariaDB utanför `APP_ENV=test`; vid `mysql://` ska `serverVersion` innehålla `mariadb`
- `PHP_CLI_BINARY=/usr/bin/php` eller motsvarande PHP CLI-binär när bakgrundsjobb startas från Apache/mod_php
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

   Standardvärdena i `.env` fungerar mot MariaDB i rootens `compose.yaml`. Skapa `.env.local` om du behöver ändra `APP_SECRET`, `DEFAULT_URI`, `DATABASE_URL`, `MAILER_DSN` eller andra lokala värden. Google Workspace OAuth är valfritt och kan konfigureras av superadmin under **E-post & inkorgar**. Inga extra Google-miljövariabler krävs om funktionen inte används eller konfigureras i admin.
   Lämna `DRIFTPUNKT_ENABLE_DEMO_DATA=0` i produktion om demoärenden inte ska skapas automatiskt i en tom databas. Sätt `DRIFTPUNKT_API_TOKEN` till ett långt slumpat värde endast om read-only API:t `/api/v1/tickets` ska aktiveras.
   För central felrapportering ställer superadmin in mottagare och svarsinkorg i den operativa cockpit-vyn. Svarsadressen måste motsvara en aktiv supportinkorg med fungerande IMAP, och `app:mail:poll` måste köras regelbundet för att e-postsvar ska visas i rapporttråden.

   Exempel på lokal MariaDB-URL:

   ```dotenv
   DATABASE_URL="mysql://driftpunkt:driftpunkt@127.0.0.1:33060/driftpunkt?serverVersion=mariadb-11.8.6&charset=utf8mb4"
   ```

3. Initiera databasen.

   För en helt ny MariaDB-installation:

   ```bash
   php bin/console app:install:fresh --env=prod
   ```

   Installationen skapar inga användarkonton. Skapa första superadmin separat; lösenordet anges dolt:

   ```bash
   php bin/console app:create-admin admin@example.com \
     --first-name=Förnamn \
     --last-name=Efternamn \
     --type=super_admin \
     --env=prod
   ```

   Kommandot skapar schema direkt från entiteterna, initierar migrationsmetadata och markerar befintliga migrationer som baseline för den nya installationen.
   Installationskommandot skapar inga konton. Efter den första inloggningen öppnar superadmin **Inställningar → Åtkomst → MFA-policy**, skapar eller importerar MFA-krypteringsnyckeln, väljer vilka roller som får använda MFA, väljer frivilligt vilka roller som måste använda MFA och väljer period för betrodd enhet. Standardperioden är 14 dagar. Ingen roll får obligatorisk MFA automatiskt vid installation eller uppgradering.

   Om du i stället arbetar mot en befintlig databas där migrationshistorik redan ska följas:

   ```bash
   php bin/console doctrine:migrations:migrate -n
   ```

4. Testkonton får endast skapas i utvecklings- eller testmiljö:

   ```bash
   php bin/console app:create-test-accounts
   ```

   eller:

   ```bash
   php bin/console app:create-admin dinmail@example.com --first-name=Förnamn --last-name=Efternamn --type=super_admin
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

## Säkerhetsuppdatera PHP-motorn

PHP 8.4.27 och 8.5.12 rättar CVE-2026-109618 och CVE-2026-109619, som kan möjliggöra kodkörning utan inloggning innan applikationskoden körs. Se [PHP:s säkerhetsmeddelande den 21 oktober 2026](https://www.php.net/archive/2026.php#2026-10-21-5). Driftpunkt stöder patchade versioner inom 8.4- och 8.5-serierna; PHP 8.5.11 uppfyller exempelvis inte kravet trots att versionsnumret är högre än 8.4.27.

`composer update`, `composer install` och Driftpunkts uppgraderingspaket uppdaterar PHP-bibliotek, men ersätter inte PHP-motorn. Uppdatera och verifiera motorn för webbservern och bakgrundsjobben **innan nya app-/vendor-paket appliceras**, så att nästa uppstart klarar de höjda plattformskraven. Vid Dockerbygge installeras beroendena i den redan uppdaterade basimagen. Ta backup och planera ett kort driftavbrott när PHP-processer eller containrar startas om.

### Debian/Ubuntu

Inventera installerade PHP-paket och uppdateringskandidater i Bash. Följande väljer enbart redan installerade paket för PHP 8.4 och 8.5, inklusive eventuellt Apache-modulstöd:

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

Kontrollera att kandidatversionerna innehåller rättningarna innan du fortsätter. Om inga paket hittas eller rättad version saknas måste PHP uppdateras via den paketering eller leverantör som faktiskt används. En dokumenterad distributionsbackport kan rätta en äldre version, men Driftpunkts versionskontroll kräver ovanstående patchnivåer.

Nästa kommando uppdaterar de installerade PHP-paketen och kan starta om tjänster. Kör det i samma Bash-session efter att den simulerade uppgraderingen har granskats:

```bash
if ((${#driftpunkt_php_packages[@]})); then
  sudo apt-get install --only-upgrade "${driftpunkt_php_packages[@]}"
fi
```

Starta sedan om den PHP-tjänst som webbplatsen använder: exempelvis `sudo systemctl restart php8.4-fpm` för FPM 8.4, `php8.5-fpm` för FPM 8.5 eller `sudo systemctl restart apache2` för Apache/mod_php. Starta även om eventuella långlivade PHP-workers. CachyOS/Arch använder i stället en samordnad systemuppgradering via `sudo pacman -Syu`; en sådan ska planeras separat eftersom fler paket än PHP kan påverkas.

### Docker/NAS

Projektets `Dockerfile` använder PHP 8.4.27. En befintlig container eller lokalt cachad PHP-image kan fortfarande innehålla en äldre motor. Kör från projektroten med NAS-konfigurationen:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env build --pull app &&
  docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env up -d --force-recreate app scheduler
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec -T app php -v
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec -T scheduler php -v
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec -T app composer check-platform-reqs --no-dev
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env ps
```

Bygget hämtar basimagen; återskapandet startar om webb och schemaläggare med den nya imagen. Båda versionskontrollerna ska visa PHP 8.4.27 och tjänsterna ska vara igång, med `app` som `healthy`. Anpassa Compose-fil och miljöfil om driftsättningen använder andra sökvägar.

Om hämtningen misslyckas, avbryt driftsättningen; använd ingen äldre PHP-tagg eller gammal lokal appimage. Den 21 oktober 2026 saknade den vanliga taggens multiarkitekturindex ännu plattformsmanifest. På en Docker-server med AMD64 kan den officiella arkitekturspecifika imagen användas tillfälligt, med samma säkerhetsnivå:

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

Kör samma versions-, plattforms- och webbkontroller efteråt. För ARM krävs en rättad image publicerad för den arkitekturen; projektets standardval behåller stöd för flera arkitekturer när upstreamindexet är komplett.

### Verifiera CLI och faktisk webb-runtime

```bash
php -r 'printf("PHP %s (%s)\n", PHP_VERSION, PHP_SAPI);'
composer check-platform-reqs --no-dev
```

CLI ska visa en tillåten patchnivå och Composer ska rapportera godkända plattformskrav. Kör plattformskontrollen även när `vendor/` levereras färdigbyggd: Composers autoloadkontroll av lägsta PHP-version ersätter inte kontrollen av respektive versionsserie och kan släppa igenom PHP 8.5.11. Kontrollera även `PHP_CLI_BINARY` och de binärsökvägar som cron/systemd använder.

`php -v` verifierar bara CLI: webbplatsens FPM-pool eller Apache-modul kan använda en annan version. Bekräfta därför version och SAPI från en riktig webbförfrågan via webbhotellets skyddade diagnos eller en tillfällig kontroll begränsad till localhost som endast visar `PHP_VERSION` och `PHP_SAPI`. Ta bort kontrollen efteråt; publicera ingen `phpinfo()`-sida. För Docker görs kontrollen efter att `app` har återskapats. Avsluta med att verifiera startsida, inloggning, ärenden och schemalagda jobb innan underhållsläget stängs av.

## Produktionsdrift

En fungerande driftsättning bör innehålla:

- webbserver som pekar på `htdocs/`
- alternativt fast webbhotellrot `/htdocs` där paketets root-`.htaccess` är aktiv
- skrivbar `var/`
- `PHP_CLI_BINARY` satt till serverns PHP CLI-binär, normalt `/usr/bin/php`, så adminytans bakgrundsjobb inte är beroende av webb-SAPI:ns `PHP_BINARY`
- skrivbar `htdocs/assets/branding` om logotyp, flikikon och branding ska kunna ändras från adminytan; uppladdade `custom-site-*`-filer är runtime-data och ska bevaras vid koduppdateringar
- MariaDB-databas med korrekt `serverVersion` i `DATABASE_URL`
- standard-superadmin och minst ett vanligt admin-konto
- process för schemalagda jobb
- worker för köade koduppdateringar om adminytans uppdateringsflöde används
- loggrotation eller motsvarande retention för `var/log/*.log`

Schemalagda jobb bör minst omfatta mailpolling, SLA-kontroll, bilagearkivering och månadsrapporter om rapportmail används. Månadsrapporten kan exempelvis köras efter månadsskifte:

```bash
php bin/console app:reports:send-monthly --env=prod
```

Om adminytans uppdateringsflöde ska användas för att applicera kodpaket måste den faktiska PHP/webbserver-användaren, normalt `www-data`, också kunna skriva till koddelarna som byts vid uppdatering: `bin/`, `config/`, `htdocs/`, `migrations/`, `public/`, `src/`, `templates/`, `composer.json`, `composer.lock` och `symfony.lock`.

På Apache-servrar med `mpm-itk` eller `AssignUserID` kan webbplatsen köras som vhost-användaren i stället för `www-data`. Kontrollera därför Apache-vhosten eller en PHP-diagnos innan rättigheter sätts, och kör manuella eftersteg som `composer install`, migrations och cache-warmup med samma användare.

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

Uppgraderingspaket är kumulativa inom Driftpunkt 1.x och innehåller `vendor/` för att NAS- och Debian-installationer inte ska behöva hämta PHP-paket från internet mitt i en uppdatering. Välj normalt senaste `driftpunkt-upgrade-<version>.zip` även om installationen ligger flera versioner efter. Adminflödet jämför paketet mot installerad kod, skriver bara nya eller ändrade filer, tar bort gamla hanterade filer och bevarar runtime-data som `.env*`, `var/` och uppladdad branding.

Releasepaketen tar bara med installations- och uppdateringsnära dokumentation från `docs/`: huvudguiden, Debian-guiden, NAS-guiden, mail-/jobbdrift, bilagearkivering, säkerhetskrav, kända driftbegränsningar och addon-releaseguiden. Produktplaner, interna specar, skärmbilder och designunderlag ska inte följa med i installations- eller uppgraderingszipparna.

Cacheuppvärmningen i Dockerbygget och uppdaterarens CLI-steg använder en separat minnesbudget på 512 MB. Composer-eftersteg får `COMPOSER_MEMORY_LIMIT=512M` som standard om inget uttryckligt värde finns. Detta ändrar inte webbserverns PHP-minnesgräns.

Rekommenderad ordning i drift:

1. skapa backup
2. aktivera underhållsläge
3. uppdatera och verifiera PHP-motorn för webb och bakgrundsjobb enligt avsnittet ovan; underhållsläge skyddar inte mot PHP-motorns sårbarheter, så begränsa vid behov webbåtkomsten på server-/proxynivå
4. stagea/applicera uppdateringspaket
5. låt uppdateringsflödet kontrollera Composer-beroenden från låsfilen, tillämpa MariaDB-migrationer och rensa/värma cachen
6. verifiera att update-worker markerar körningen som `completed`
7. verifiera inloggning, tickets och mail
8. avaktivera underhållsläge och återöppna extern åtkomst när kontrollerna är godkända

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
