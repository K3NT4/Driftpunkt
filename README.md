# Driftpunkt

<p align="center">
  <img src="docs/public-assets/branding/logo-wide.png" alt="Driftpunkt" width="520">
</p>

<p align="center">
  Symfony-baserat ticketsystem för drift, support och kunddialog.
</p>

Driftpunkt är ett Symfony-baserat support- och driftsystem med publik webb, kundportal, teknikerportal och adminyta i samma produkt.

README:n beskriver nuläget i koden i detta repo: fungerande MVP-flöden för ticketing, kunddialog, e-postingest, driftstatus, administration och release/driftarbete. Miljöspecifika integrationer och viss intern driftsetup kan fortfarande leva utanför repo eller exportflöde.

## Översikt

Driftpunkt innehåller bland annat:

- publik webb med startsida, driftstatus, nyheter, sök, kontakt och policy-sidor
- kundkonto med självregistrering, lösenordsåterställning och kundportal för ticketdialog
- teknikerportal för ticketkö, prioritering, kommentarer, SLA och kunskapsbasarbete
- adminyta för identitet, företag, team, mail, innehåll, driftstatus, databas, jobb och uppdateringar
- e-postflöden med spool-ingest, mailbox-polling och draftgranskning för osäkra fall
- kunskapsbas, nyhetsmodul och statuskommunikation i samma produkt
- underhållsläge, bilagearkivering, bakgrundsjobb och release-/uppgraderingspaket

## Vad man kan göra

### Publik webb

- visa startsida med statusöversikt och senaste nyheter
- läsa nyheter, publik kunskapsbas och publika policy-sidor
- använda sök och kontaktsida utan separat frontend
- visa driftstatus och underhållsinformation publikt

### Kundflöden

- registrera privatkundskonto när funktionen är aktiverad
- logga in, återställa lösenord och följa egna eller företagsdelade tickets
- kommentera tickets kunden har access till
- läsa kundsynlig kunskapsbas och statusinformation

### Tekniker- och adminflöden

- skapa, ta över, uppdatera och kommentera tickets
- styra visibility, prioritet, påverkan, request type, routing och SLA
- hantera företag, användare, team och andra identitetsobjekt
- skapa och publicera nyheter och kunskapsbasinnehåll
- arbeta med telefonstödsdata i admin
- konfigurera mail, supportinkorgar, driftstatus, inställningar och underhåll

### Drift och automation

- läsa in inkommande mail via spool eller polling av supportinkorgar
- lägga osäkra mail i draftgranskning innan de blir kundsynliga tickets
- köra SLA-kontroll, bilagearkivering, databasjobb och post-update tasks via CLI
- bygga installations- och uppgraderingspaket
- stagea och applicera koduppdateringsflöden i admin/drift

## Begränsningar i nuläget

- inget publikt webbformulär för att skapa helt nya tickets utan inloggning
- inget externt API för integrationer
- ingen full rapport- eller BI-modul
- driftmodellen förutsätter att schemalagda jobb och hardening satts upp externt

## Installationskrav

Driftpunkt är en serverrenderad Symfony 8-applikation. Det finns ingen separat Node-, npm- eller frontend-build som krävs för att köra sidan i nuläget.

Minimikrav för lokal utveckling och serverdrift:

- PHP 8.4 eller senare
- Composer 2
- MariaDB 10.11 eller senare, rekommenderat MariaDB 11 för lokal Docker-miljö
- PHP-tilläggen `ctype`, `iconv`, `intl`, `pdo_mysql` och `zip`
- en webbserver som kan köra PHP och peka dokumentroten mot `public/`, till exempel Symfony CLI, PHP:s inbyggda server, Apache eller appcontainern i Docker
- skrivbar `var/`-katalog för cache, loggar, uppladdningar och delade driftfiler
- om adminytans webbaserade uppdateringsflöde ska användas måste PHP/webbserver-användaren, normalt `www-data`, även kunna skriva till kodfilerna som uppdateras: `bin/`, `config/`, `migrations/`, `public/`, `src/`, `templates/`, `composer.json`, `composer.lock` och `symfony.lock`

Rekommenderat för produktion:

- HTTPS/TLS framför applikationen
- ett unikt `APP_SECRET` i `.env.local` eller riktiga miljovariabler
- en riktig `DATABASE_URL` mot MariaDB
- SMTP-konfiguration via `MAILER_DSN` om appen ska skicka e-post
- schemalagda jobb för mailpolling, SLA-kontroll och bilagearkivering via systemd, cron eller Docker-scheduler
- regelbunden backup av MariaDB och `var/share`
- välj driftmodell för kodägarskap: webbaserad uppdatering kräver skrivbar applikationskod för `www-data`, medan en hårdare låst server kan låta `root` äga kodfilerna och i stället uppdateras via SSH/deploy-script

Valfria verktyg:

- Symfony CLI för smidig lokal serverstart
- Docker och Docker Compose för lokal MariaDB eller komplett NAS-/serverdrift
- PHPUnit för testkörning via `php bin/phpunit`

## Skärmbilder

### Startsida

![Driftpunkt startsida](docs/public-assets/screenshots/homepage.png)

### Inloggning

| Admin | Tekniker |
| --- | --- |
| ![Admin login](docs/public-assets/screenshots/login-admin.png) | ![Tekniker login](docs/public-assets/screenshots/login-technician.png) |

| Kund |
| --- |
| ![Kund login](docs/public-assets/screenshots/login-customer.png) |

### Portaler

| Kundportal | Teknikerportal |
| --- | --- |
| ![Kundportal](docs/public-assets/screenshots/customer-portal.png) | ![Teknikerportal](docs/public-assets/screenshots/technician-portal.png) |

### Adminvy

![Admin dashboard](docs/public-assets/screenshots/admin-dashboard.png)

## Snabbstart

1. Installera PHP-beroenden

   ```bash
   composer install
   ```

2. Konfigurera miljö

   Standardvärdena i `.env` fungerar för lokal utveckling mot MariaDB från `compose.yaml`. Skapa `.env.local` om du vill ändra till exempel `DATABASE_URL`, `APP_SECRET`, `MAILER_DSN` eller `DEFAULT_URI`.

3. Starta MariaDB om du använder lokal Docker-miljö

   ```bash
   docker compose up -d database
   ```

   Om du inte använder Docker, skapa en MariaDB-databas och uppdatera `DATABASE_URL`.

4. Initialisera databasen

   För en helt ny installation, särskilt på MariaDB/MySQL:

   ```bash
   php bin/console app:install:fresh
   ```

   Det kommandot säkerställer alltid reserv-superadmin och ett vanligt admin-konto. Det skapar också standardkonton för tekniker och kund om du inte uttryckligen hoppar över dem med `--skip-test-accounts`.

   Om du arbetar mot en befintlig databas med migrationshistorik:

   ```bash
   php bin/console doctrine:migrations:migrate -n
   ```

5. Skapa testkonton eller en första admin

   ```bash
   php bin/console app:create-test-accounts
   ```

   eller

   ```bash
   php bin/console app:create-admin dinmail@example.com DittLösenord123 Förnamn Efternamn super_admin
   ```

6. Starta sidan

   ```bash
   symfony server:start
   ```

   Om Symfony CLI saknas:

   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

7. Öppna `http://127.0.0.1:8000`

## Snabbstart med Docker Compose

Rootens `compose.yaml` startar MariaDB på port `33060` och mailtestning för lokal utveckling. NAS-/serverdrift med appcontainer finns i `deploy/nas/compose.yaml`.

1. Starta hjälptjänsterna

   ```bash
   docker compose up -d
   ```

2. Installera beroenden och initiera MariaDB

   ```bash
   composer install
   php bin/console app:install:fresh
   ```

3. (Valfritt) skapa testkonton

   ```bash
   php bin/console app:create-test-accounts
   ```

4. Öppna `http://127.0.0.1:8000`

## NAS med Docker Compose

För en NAS eller annan Docker-baserad server finns en komplett compose-setup med app, MariaDB, persistenta volymer och scheduler:

- [NAS-installation med Docker Compose](docs/nas_docker_setup.md)
- `deploy/nas/compose.yaml`
- `deploy/nas/.env.example`

Kort version:

```bash
cp deploy/nas/.env.example deploy/nas/.env
nano deploy/nas/.env
mkdir -p deploy/nas/data/var deploy/nas/data/mariadb
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env up -d --build
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec app php bin/console app:install:fresh --env=prod
```

## Debian-server

För en vanlig Debian-server utan Docker finns en konkret runbook och färdiga mallar:

- [Debian-server: krav och installation](docs/debian_server_setup.md)
- `deploy/debian/app.env.example` för `.env.local`
- `deploy/debian/apache-driftpunkt.conf` för Apache
- `deploy/debian/setup.sh` för basinstallation av paket, Composer, Apache-site och systemd-timers

Kort version:

```bash
sudo mkdir -p /var/www/driftpunkt
sudo chown "$USER":www-data /var/www/driftpunkt
git clone <repo-url> /var/www/driftpunkt
cd /var/www/driftpunkt
DOMAIN=driftpunkt.example.com sudo -E bash deploy/debian/setup.sh
sudo nano /var/www/driftpunkt/.env.local
sudo -u www-data php bin/console app:install:fresh --env=prod
```

## Testkonton

Om du kör `app:create-test-accounts` skapas:

| Roll | E-post | Lösenord |
| --- | --- | --- |
| Reserv super admin | `kenta@spelhubben.se` | Dolt, byte krävs vid första inloggning |
| Admin | `admin@test.local` | `AdminPassword123` |
| Tekniker | `tech@test.local` | `TechPassword123` |
| Kund | `customer@test.local` | `CustomerPassword123` |

`app:install:fresh` säkerställer alltid reserv-superadmin och admin-kontot. Tekniker- och kundkontot skapas automatiskt om du inte anger `--skip-test-accounts`.
Alla standardkonton markeras för lösenordsbyte vid första inloggningen.

## Viktiga Kommandon

```bash
php bin/console app:create-test-accounts
php bin/console app:system-accounts:ensure
php bin/console app:install:fresh
php bin/console app:create-admin <email> <lösenord> <förnamn> <efternamn> <admin|super_admin>
php bin/console app:mail:ingest <spoolfil>
php bin/console app:mail:poll
php bin/console app:check-ticket-sla
php bin/console app:archive-ticket-attachments
php bin/console app:database-maintenance:run <job-id>
php bin/console app:post-update:run <run-id>
php bin/console app:code-update:apply-run <run-id>
php bin/console app:maintenance status
php bin/console app:release:build-packages
composer export:public-repo
php bin/phpunit
```

## Releasepaket

Releaseversionen hämtas normalt från `version` i `composer.json`. Höj den inför varje släpp, till exempel från `1.0.0` till `1.0.1`, så får både adminytan och zip-filerna samma versionsnummer.

Bygg båda paketen:

```bash
composer build:packages
```

Bygg bara uppgraderingspaketet:

```bash
composer build:upgrade-package
```

Bygg bara installationspaketet:

```bash
composer build:install-package
```

Både installations- och uppgraderingspaket innehåller `vendor/`, så en NAS eller Debian-server slipper hämta Composer-paket från internet under själva uppdateringen.

Uppdateringar från adminytan kör förkontroll mot MariaDB, stoppar parallella uppdateringar, tar kodbackup, kör obligatoriska eftersteg och markerar inte versionen som klar förrän migrationer och cache-rensning har lyckats.

## Publik Export

Skapa en publik arbetskopia från den privata repot:

```bash
composer export:public-repo
```

Peka exporten direkt mot en lokal klon av den publika GitHub-repot:

```bash
php bin/console app:repo:export-public --output-dir=/path/to/-Driftpunkt
```

Exporten behåller publika projektmappar men filtrerar bort privata delar som:

- `src/Module/Mail`
- `src/Module/Portal`
- interna release- och driftverktyg i `src/Module/System/Service`
- `templates/portal`
- `templates/emails`
- `deploy/`

Rekommenderat arbetsflöde:

1. Utveckla i `Driftpunkt-privat`
2. Exportera till den publika repot
3. Granska diffen noggrant
4. Commit:a och pusha när exporten ser ren ut

## Dokumentation

Dokumentationen i `docs/` beskriver nuläget i koden, inte en framtida målbild:

- `docs/driftpunkt_ticket_system_spec.md`
- `docs/product_scope_and_mvp.md`
- `docs/installation_and_deployment.md`
- `docs/roles_and_permissions.md`
- `docs/data_model.md`
- `docs/ticket_lifecycle_and_visibility.md`
- `docs/customer_portal_experience.md`
- `docs/admin_information_architecture.md`
- `docs/mail_configuration_guide.md`
- `docs/mail_processing_rules.md`
- `docs/mail_polling_operations.md`
- `docs/ticket_attachment_archiving_operations.md`
- `docs/operational_model.md`
- `docs/security_requirements.md`
- `docs/testing_and_quality.md`
- `docs/known_limitations.md`
- `docs/documentation_reuse_and_plan.md`

Bra startpunkter beroende på vad du vill göra:

- produktomfång och nuläge: `docs/driftpunkt_ticket_system_spec.md`
- installation och drift: `docs/installation_and_deployment.md`
- roller och behörigheter: `docs/roles_and_permissions.md`
- mailflöden: `docs/mail_processing_rules.md`
- daglig driftmodell: `docs/operational_model.md`
