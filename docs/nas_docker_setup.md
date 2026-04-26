# NAS-installation med Docker Compose

Den här guiden är för att sätta upp Driftpunkt på nytt på en NAS efter att containrar, volymer eller delade mappar försvunnit. Den använder Docker Compose och sparar databas samt appens `var/` i synliga mappar under `deploy/nas/data/`.

## Krav

- NAS med Docker/Container Manager och Docker Compose
- shell-access via SSH rekommenderas
- Driftpunkt-koden kopierad eller klonad till NAS:en
- MariaDB via den medföljande compose-stacken eller extern MariaDB 10.11+
- `DATABASE_URL` som pekar på MariaDB och anger `serverVersion` med `mariadb`
- om adminytans uppdateringsflöde ska användas måste appcontainerns `www-data` kunna skriva till applikationskoden i `/var/www/html`, inte bara till `var/`
- om adminytans uppdateringsflöde används bör en NAS-schemalagd uppgift eller cron-worker köra `app:code-update:apply-run` för köade uppdateringar

Efter ombyggd eller ny appcontainer kan kodfilerna behöva ägarrättas innan ett uppdateringspaket appliceras:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec -u root app sh -lc 'chown -R www-data:www-data /var/www/html && chmod -R u+rwX /var/www/html'
```

## Starta från noll

1. Gå till projektet på NAS:en:

   ```bash
   cd /sökväg/till/Driftpunkt
   ```

2. Skapa miljofilen:

   ```bash
   cp deploy/nas/.env.example deploy/nas/.env
   ```

3. Redigera `deploy/nas/.env` och byt minst:

   - `APP_SECRET`
   - `DEFAULT_URI`
   - `MARIADB_PASSWORD`
   - `MARIADB_ROOT_PASSWORD`
   - samma databaslösenord i `DATABASE_URL`
   - `MAILER_DSN` och `MAILER_FROM` när utgående mail ska fungera
   - `RESERVED_SUPER_ADMIN_PASSWORD` om reservkontot ska få ett annat förvalt dolt lösenord

4. Bygg och starta:

   ```bash
   mkdir -p deploy/nas/data/var deploy/nas/data/mariadb
   docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env up -d --build
   ```

5. Initiera databasen:

   ```bash
   docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec --user www-data app php bin/console app:install:fresh --env=prod
   ```

6. Öppna adressen från `DEFAULT_URI`, till exempel:

   ```text
   http://nas-ip-eller-domän:8080
   ```

## Standardkonton

`app:install:fresh` säkerställer alltid reserv-superadmin och ett vanligt admin-konto. Tekniker- och kundkonton skapas om du inte anger `--skip-test-accounts`:

| Roll | E-post | Lösenord |
| --- | --- | --- |
| Reserv super admin | `kenta@spelhubben.se` | Dolt, byte krävs vid första inloggning |
| Admin | `admin@test.local` | `AdminPassword123` |
| Tekniker | `tech@test.local` | `TechPassword123` |
| Kund | `customer@test.local` | `CustomerPassword123` |

Alla standardkonton markeras för lösenordsbyte vid första inloggningen.

Skapa hellre en riktig admin efter installationen:

```bash
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec --user www-data app php bin/console app:create-admin admin@example.com 'BytDettaLösenord123' Förnamn Efternamn super_admin --env=prod
```

## Bakgrundsjobb

Compose-filen startar en `scheduler`-container som kör:

- `app:mail:poll` varje minut
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
- kör schemalagda Symfony-kommandon som `www-data`

## Backup

Ta backup på både databasen och appens `var/`-mapp.

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
- `deploy/nas/data/var`
- `manifest.txt` med tidpunkt, compose-fil och git-commit när det finns

Det lägger också ett enkelt lås så två backuper inte körs samtidigt, verifierar arkivet med `tar -tzf` och rensar backuper äldre än 14 dagar. Ändra retention vid behov:

```bash
BACKUP_RETENTION_DAYS=30 bash deploy/nas/backup.sh
```

Restore från ett backup-arkiv:

```bash
CONFIRM=restore bash deploy/nas/restore.sh deploy/nas/backups/driftpunkt-YYYYMMDD-HHMMSS.tar.gz
```

Restore stoppar app och scheduler, läser in `database.sql`, återställer `data/var` och startar sedan stacken igen. Den gamla `data/var` flyttas till en tidsstamplad `var.before-restore-*`-mapp.

Viktiga mappar:

- `deploy/nas/data/var` för appfiler, bilagor, cache och loggar
- `deploy/nas/data/mariadb` för MariaDB-data

## Uppdatering

Ta backup före uppdatering:

```bash
bash deploy/nas/backup.sh
```

Rekommenderat flöde för paketuppdatering är adminytan:

1. aktivera underhållsläge
2. ladda upp uppgraderingspaketet under Admin -> Uppdateringar
3. applicera versionen och låt förkontroller, MariaDB-migrationer och cache-rensning gå klart
4. verifiera startsida, login, ärenden och mailflöden
5. bekräfta verifieringen så underhållsläget stängs av

Uppgraderingspaket ska innehålla `vendor/`. NAS:en ska därför inte behöva hämta Composer-paket från GitHub under själva uppdateringen.

Manuell uppdatering via shell:

```bash
git pull
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env up -d --build
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec --user www-data app php bin/console doctrine:migrations:migrate -n --env=prod
docker compose -f deploy/nas/compose.yaml --env-file deploy/nas/.env exec --user www-data app php bin/console cache:clear --env=prod
```
