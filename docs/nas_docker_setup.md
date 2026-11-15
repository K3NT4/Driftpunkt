# NAS-installation med Docker Compose

Den här guiden gäller den medföljande **avbildningsbaserade** Compose-stacken, på en ny NAS eller i en separat tom projektkatalog. För enklaste nyinstallation: börja i [INSTALL.md](../INSTALL.md). Inga befintliga NAS-containrar ändras av att bara läsa guiden.

## Krav

- Docker/Container Manager och Docker Compose v2, SSH-terminal och rättighet att köra Docker.
- Installationspaketet uppackat med dolda filer i en egen katalog. Kör kommandon från paketrot, inte katalogen ovanför.
- Paketets PHP/GD-krav uppfylls vid imagebygget; `manage.sh check` verifierar PHP och PNG-filer. Aktiva webb-PHP och den faktiska installationens funktioner behöver också verifieras.
- Använd den genererade MariaDB-konfigurationen för nyinstallation. Byt inte avbildningsserie för en befintlig databas utan en separat migrations-/backupplan.

## Kort nyinstallation

Byt adress och administratörens e-post. Vid extern HTTPS-proxy används den publika HTTPS-adressen i `init`. HTTP/IP är för avgränsade tester, inte verklig kunddrift.

```bash
bash deploy/nas/manage.sh init http://DIN-NAS-IP:8080
bash deploy/nas/manage.sh install
bash deploy/nas/manage.sh admin din-adress@example.com
```

`init` genererar slumpad `APP_SECRET` och två databaslösenord i en privat miljöfil (600). Samma applikationslösenord skrivs i `DATABASE_URL`; inget behöver kopieras mellan olika inställningar. En befintlig miljöfil skrivs inte över. Granska `DEFAULT_URI`, `DRIFTPUNKT_PUBLIC_BASE_URL`, `APP_PORT` och `MAILER_FROM`. SMTP är avstängt (`null://null`) tills du konfigurerar det.

`install` startar bara databas och app före initiering. Databasen måste vara tom. Vid en avbruten **nyinstallation** används uttryckligen `install --resume`; det kommandot raderar inte befintliga tabeller utan stoppas av applikationens skydd. Använd inte detta vid uppgradering av ett befintligt system.

`admin` frågar efter lösenord dolt. Öppna adressen, kontrollera kontot och ställ in MFA under **Inställningar → Åtkomst → MFA-policy**. Konfigurera riktiga mottagare och inkorgar innan utskick tillåts. Därefter:

```bash
bash deploy/nas/manage.sh check
bash deploy/nas/manage.sh start
bash deploy/nas/manage.sh status
```

Jobben aktiveras först med `start`. På NAS där Docker kräver root används `sudo bash ...`. Skriptet konfigurerar inte automatiskt brandvägg, TLS eller omvänd proxy.

### Lagring och befintliga installationer

Databas, `var/` och uppladdad webbplatsbranding ligger beständigt i `deploy/nas/data/{mariadb,var,branding}`. De inbyggda standardlogotyperna läggs till utan att skriva över redan sparade filer.

**Innan en äldre stack får den nya branding-monteringen:** säkerhetskopiera och kopiera dess nuvarande `/var/www/html/htdocs/assets/branding` till `deploy/nas/data/branding`. En tom ny bind-montering döljer annars gamla filer inne i containern. Ändra inte en äldre stacks Compose-filer eller databasavbildning genom att blint kopiera denna guide; inventera dess verkliga volymer först.

## Byggkontext och paketfiler

Både installations- och uppgraderingszippen innehåller `.dockerignore` bredvid `Dockerfile`. Behåll filen vid uppackning och kopiering, även om filhanteraren döljer punktfiler. Bygg från den uppackade paketrot där båda filerna finns, inte från katalogen ovanför.

`.dockerignore` håller följande utanför byggkontexten och imagen:

- `db/`, `var/`, `backup/`, `backups/` samt databasdumpar i projektroten (`*.sql`, `*.sql.gz`, `*.sql.zip`).
- `deploy/nas/data/` och `deploy/nas/backups/`, inklusive MariaDB-data och återställda driftmappar.
- `public/uploads/`, `htdocs/uploads/` och uppladdade `custom-site-*`-logotyper; de medföljande standardlogotyperna finns kvar.
- Git-metadata, lokala miljööverstyrningar, `deploy/nas/.env`, loggar och byggartefakter enligt filens övriga regler.

Källkod, `vendor/`, Composer-filer, webbassets och `deploy/php`/`deploy/apache` ska finnas med. Uteslut inte hela `deploy/` eller `htdocs/assets/`. Lägg egen körtidsdata i de dokumenterade driftmapparna; egna lagringsplatser måste också läggas till i `.dockerignore`. Paketbyggaren hoppar dessutom över `deploy/nas/data/` och `deploy/nas/backups/` utan att läsa deras innehåll när zipparna skapas.

Dockerfilen tillåter Composer-pluginer uttryckligen med `COMPOSER_ALLOW_SUPERUSER=1` för själva `composer install`-steget, som körs som root vid imagebygget. Det gör att den tillåtna Symfony Flex-pluginen kan köra `symfony-cmd` och installera assets. Inställningen är inte en permanent miljövariabel i den färdiga containern. Composer använder den befintliga låsfilen och `--no-dev`, utan versionsuppdatering av beroenden.

Kontrollera punktfilen före bygget och spara byggloggen:

```bash
test -f .dockerignore && test -f Dockerfile
docker build --pull -t driftpunkt:test . 2>&1 | tee /tmp/driftpunkt-docker-build.log
```

Kontrollera att Dockerbygget avslutas utan fel och att `transferring context` visar en rimlig kodstorlek, inte flera GB databas eller backup. Storleken beror bland annat på om `vendor/` finns och om BuildKit redan har cache. Bygget skapar en lokal testimage; det startar eller ersätter inga driftcontainrar. Om en pipeline används i ett skript bör den köras med Bash och `set -o pipefail` så att ett byggfel inte döljs av `tee`.

Om `.dockerignore` saknas i ett äldre paket: bygg inte förrän den aktuella filen har lagts tillbaka. Uppdaterade paket behöver genereras om för att innehålla rättningen. Redan byggda image-lager ändras inte av en ny ignore-fil; bygg om imagen från den korrigerade källan.

### Verifiering av paketrättningen, 2026-11-11

- `app:release:build-packages`, Composer-kommandona `build:packages`/`build:install-package`/`build:upgrade-package` och `app:repo:export-public` använder samma `ReleasePackageBuilder`. Den publika exportören kopierar dess färdiga zippar; GitHub-arbetsflödet publicerar också redan byggda zippar. Övriga zip-skrivare hanterar kod-/databasbackuper och bilagearkiv, inte installations- eller uppgraderingspaket.
- Både repo och uppackat installationspaket från `app:repo:export-public` byggdes med `docker build --pull -t driftpunkt:test .`, exitkod 0. Composer körde produktionsinstallation, cache-rensning och Flex-steget `assets:install` utan fel.
- Nio kontrollfiler på totalt **576 MiB per byggrot** placerades i de uteslutna driftmapparna och som SQL-dump i roten. Dockerfiltrering och kontroll av de färdiga imagerna visade att inga kontrollfiler följde med. Standardlogotyp, beroenden och byggkonfiguration fanns kvar; `composer check-platform-reqs --no-dev` passerade i båda imagerna.

| Byggkälla | Filinnehåll efter Dockers ignore-filter | Rapporterad överföring vid slutbygget |
| --- | ---: | ---: |
| Repo med utvecklingsberoenden, tester och interna dokument | 90 136 209 byte (90,14 MB) | 858,26 kB, med varm BuildKit-cache |
| Uppackat installationspaket | 47 938 458 byte (47,94 MB) | 48,80 MB |

Filinnehållet mättes genom en separat lokal BuildKit-export med `FROM scratch` och `COPY . /`, med samma `.dockerignore`. Summan av filernas storlek skiljer sig från överförda byte och arkivens storlek; BuildKit återanvänder dessutom redan överförda filer. Siffrorna gäller denna lokala kontroll, inte en generell storleksgräns.

Berörda tester kördes i en PHPUnit-process med:

```bash
php -d memory_limit=128M vendor/bin/phpunit tests/Service/ReleasePackageBuilderTest.php tests/Service/PublicRepoExporterTest.php tests/Service/CodeUpdateManagerTest.php
```

Resultat: **16 tester, 503 assertions, godkända**, samt godkänd PHP-syntax och `git diff --check`. Testerna verifierar båda pakettypernas `.dockerignore`, viktiga exkluderingsmönster, nödvändiga byggfiler, manifest och bortfiltrerad NAS-data. Lokala bygg-, image- och testloggar finns under `var/docker-verification/`; de ingår inte i paketen eller Git. Appversion och låsta beroendeversioner är oförändrade. Ingen NAS-driftsättning har gjorts.

## Första superadmin

`app:install:fresh` skapar aldrig användarkonton. Skapa första superadmin separat och ange lösenordet dolt:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec --user www-data app php bin/console app:create-admin admin@example.com --first-name=Förnamn --last-name=Efternamn --type=super_admin --env=prod
```

Logga in och öppna därefter **Inställningar → Åtkomst → MFA-policy** för att skapa eller importera MFA-nyckeln och välja tillgänglighet, frivilliga rollkrav och period för betrodd enhet. Ingen roll får obligatoriskt MFA automatiskt.

## Bakgrundsjobb

Compose-filen startar en `scheduler`-container som kör:

- `app:mail:poll` varje minut
- `app:internal-reports:retry-notifications` varje minut; endast förfallna misslyckade notifieringar behandlas
- `app:check-ticket-sla` varje minut
- `app:archive-ticket-attachments` runt 02:15

Köade koduppdateringar från adminytan körs inte av scheduler-containern automatiskt. Använd NAS:ens schemalagda uppgifter eller cron enligt `docs/installation_and_deployment.md` om uppdateringar ska appliceras från adminytan.

Kontrollera loggar:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env logs -f app scheduler
```

## Stabilitet

Compose-filen gör följande för en lugnare NAS-drift:

- startar om containrar automatiskt med `restart: unless-stopped`
- väntar på frisk databas innan appen startar fullt
- kör en HTTP-healthcheck mot appen
- rättar ägare på `var/` till `www-data` vid start
- rättar ägare på `htdocs/assets` till `www-data` så brandinguppladdning fungerar
- kör schemalagda Symfony-kommandon som `www-data`

## Backup

Ta backup på databas, `var/`, logotyper och privata miljöhemligheter. Arkiven innehåller hemligheter och ska förvaras skyddat, utanför publika kataloger.

Databasdump:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec database mariadb-dump -u root -p driftpunkt > driftpunkt.sql
```

Samlad backup till `deploy/nas/backups/`:

```bash
bash deploy/nas/backup.sh
```

Backupscriptet skapar ett arkiv med:

- MariaDB-dump med `--single-transaction`, rutiner, triggers och events
- `deploy/nas/data/var` och eventuell `data/branding`
- privat miljöfil och Compose-konfiguration under `configuration/`
- `manifest.txt` med tidpunkt, compose-fil och git-commit när det finns

Det lägger också ett enkelt lås så två backuper inte körs samtidigt, verifierar arkivet med `tar -tzf` och rensar backuper äldre än 14 dagar. Ändra retention vid behov:

```bash
BACKUP_RETENTION_DAYS=30 bash deploy/nas/backup.sh
```

Restore från ett backup-arkiv:

```bash
CONFIRM=restore bash deploy/nas/restore.sh deploy/nas/backups/driftpunkt-YYYYMMDD-HHMMSS.tar.gz
```

Restore kräver rätt programversion och matchande `APP_SECRET`; hemligheten skrivs inte över automatiskt. Restore stoppar app och scheduler, läser in `database.sql`, återställer `data/var` och eventuell branding och startar sedan stacken igen. Den gamla `data/var` flyttas till en tidsstamplad `var.before-restore-*`-mapp.

Viktiga mappar:

- `deploy/nas/data/var` för appfiler, bilagor, cache och loggar
- `deploy/nas/data/mariadb` för MariaDB-data

## Uppdatering

### Om Docker-bygget stannar med `symfony-cmd: not found`

Om loggen samtidigt anger att Composer har inaktiverat plugins för root saknas Symfony Flex vid körningen av installationsskripten. Dockerfile anger därför `COMPOSER_ALLOW_SUPERUSER=1` för just installationskommandot i byggcontainern. Behåll `allow-plugins` i `composer.json`; kringgå inte felet med `--no-scripts` eller genom att ignorera PHP-kraven.

`.dockerignore` utesluter `deploy/nas/data/` och `deploy/nas/backups/` så att databasfiler, bilagor och backuper inte kopieras in i imagen. Det raderar inga NAS-filer och ändrar inte containrarnas volymmonteringar. Om byggkontexten fortfarande är flera GB, kontrollera vilka andra stora mappar som ligger i projektkatalogen innan du bygger igen.

Bygg om med kommandona nedan efter att rättningen hämtats. Ett lyckat bygge ändrar inte den redan körande containerns PHP-version; både `app` och `scheduler` måste därefter återskapas och verifieras.

Ta backup före uppdatering:

```bash
bash deploy/nas/backup.sh
```

### Välj rätt uppdateringsmetod

Den medföljande Compose-stacken har **koden i avbildningen**. Webbens kodbyte uppdaterar inte schemaläggarens avbildning och ändringen kan försvinna när containern återskapas. Använd därför avbildningsbaserad uppgradering för denna stack: säkerhetskopiera, lägg den nya koden i rätt byggrot, bygg ny avbildning, uppdatera både app och schemaläggare, kör alla migrationer/cache-steg och verifiera innan verksamhetsjobben återupptas. Använd inte `manage.sh install` för detta.

Äldre egna stackar som delar **hela programkoden** mellan containrarna kan använda det granskade superadminflödet med kodbackup, underhåll och obligatoriska eftersteg. Det är ett annat driftsätt och ska inte blandas med den medföljande stackens avbildningsuppgradering. Se [2.0-checklistan](upgrade_to_2_0_0.md).

PHP-motorn måste vara minst 8.4.27 inom 8.4-serien eller 8.5.12 inom 8.5-serien för rättningarna av CVE-2026-109618 och CVE-2026-109619. Apppaketet och Composer uppdaterar inte PHP-motorn; uppdatera och verifiera den innan nya app-/vendor-paket appliceras. En gammal container eller cachad basimage ersätts först genom ombyggnad med `--pull` och återskapande av både `app` och `scheduler`. Det ger ett kort driftavbrott; ta backup och använd underhållsläge. Avbryt om basimagen inte kan hämtas. Se [PHP-uppdatering och verifiering](installation_and_deployment.md#säkerhetsuppdatera-php-motorn) för faktisk webb-PHP och den tillfälliga, uttryckligen AMD64-begränsade vägen när upstreamindexet saknar manifest.

Manuell uppdatering via shell:

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
