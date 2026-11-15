# Börja här – installera Driftpunkt

Denna guide gäller en **ny installation** från installationspaketet. Packa upp ZIP-filen och gå in i katalogen som innehåller `composer.json`, `Dockerfile` och `deploy/`. Behåll dolda filer, särskilt `.dockerignore`. Inga riktiga användare eller demoärenden skapas automatiskt.

Har du redan Driftpunkt och data? Använd [uppgraderingsguiden](docs/upgrade_to_2_0_0.md), inte nyinstallation eller `app:install:fresh`.

## NAS med Docker / Container Manager

Krav: SSH-terminal, Docker och Docker Compose v2. Lägg till `sudo` före `bash` om Docker kräver det. Kör följande från den uppackade paketkatalogen:

```bash
bash deploy/nas/manage.sh init http://DIN-NAS-IP:8080
bash deploy/nas/manage.sh install
bash deploy/nas/manage.sh admin din-adress@example.com
```

1. **init** skapar en privat miljöfil med slumpade hemligheter och matchande databaslösenord. Befintlig fil skrivs aldrig över. Ange den adress du faktiskt ska använda. Standardport på NAS: 8080; ändra `APP_PORT` i `deploy/nas/.env` om den är upptagen. Vid omvänd proxy anges den publika HTTPS-adressen, medan `APP_PORT` är den interna värdporten.
2. **install** bygger avbildningen, väntar på databasen, startar webbappen och initierar en tom databas. Schemaläggaren väntar till senare. Befintliga databasfiler stoppas före start; en avbruten nyinstallation återupptas uttryckligen med `install --resume`. Databasinitieringen vägrar alltid befintliga tabeller.
3. **admin** frågar efter lösenord med dold inmatning. Använd minst 12 tecken. Lösenord ska inte anges i kommandoraden.

Öppna adressen, logga in och verifiera första kontot. Konfigurera SMTP och inkorgar först när du vill tillåta riktiga utskick. Därefter:

```bash
bash deploy/nas/manage.sh check
bash deploy/nas/manage.sh start
bash deploy/nas/manage.sh status
```

`check` ska sluta utan FEL. `start` aktiverar återkommande verksamhetsjobb. Ingen brandvägg, portvidarebefordran eller TLS-konfiguration ändras av verktyget. Använd HTTPS före verkliga användare och data. För tillfälliga isolerade tester kan HTTP/IP användas.

[Full NAS-guide och backup](docs/nas_docker_setup.md). Spara `deploy/nas/.env`, data och logotyper skyddat. **Använd inte webbens kodbyte med den medföljande avbildningsbaserade Compose-stacken**; uppdatera både app och schemaläggarens avbildning enligt guiden. Äldre specialstackar med delad programkod är ett annat driftsätt.

## Debian 13 utan Docker

Krav: root/sudo, en ny eller inventerad server och installationspaketet under exempelvis `/var/www/driftpunkt`. Följ dessa faser:

1. **Förbered servern.** Byt domänen till din egen. Kommandot installerar systempaket och förbereder en saknad miljöfil med slumpad `APP_SECRET`:

   ```bash
   cd /var/www/driftpunkt
   sudo env DOMAIN=support.dindoman.se bash deploy/debian/setup.sh
   ```

   Saknas konfiguration av databasen avslutas skriptet med ett tydligt nästa steg; inga tabeller eller konton skapas. På en tillfällig IP-baserad testserver används `DOMAIN=DIN-SERVER-IP PUBLIC_URL=http://DIN-SERVER-IP`.

2. **Konfigurera databasen och miljön.** Följ [databassteget i Debian-guiden](docs/debian_server_setup.md#snabb-installation). Byt databaslösenordet på ett ställe i MariaDB och använd samma i `.env.local`. Bevara den genererade `APP_SECRET`. Kör sedan samma setup-kommando igen. Skriptet installerar beroenden, vhost och timerfiler men startar inga verksamhetsjobb.
3. **Initiera den tomma databasen och skapa superadmin:**

   ```bash
   sudo -u www-data php bin/console app:install:fresh --env=prod
   sudo -u www-data php bin/console app:create-admin din-adress@example.com --type=super_admin --env=prod
   ```

   Lösenordet anges dolt. Databasinitieringen nekar befintliga tabeller.
4. **Verifiera, slå på HTTPS och starta jobben** enligt [Debian-guiden](docs/debian_server_setup.md). Testa inloggning, skydda kontot med MFA och kontrollera båda PHP-miljöerna. `bash deploy/debian/setup.sh --check` gör en läsande lokal PHP-/filkontroll.

PHP måste uppfylla paketets exakta krav, inklusive GD med JPEG/PNG/FreeType. Skripten stannar om PHP är för gammal; de lägger inte tyst till externa paketförråd. På Debian 13 används distributionspaketen och dess säkerhetsuppdateringar.

## Om något stoppar

- Läs det första felet, inte bara sista raden. Fortsätt inte med databasinitiering på en befintlig installation.
- NAS: `bash deploy/nas/manage.sh status` och `bash deploy/nas/manage.sh logs`.
- Debian: `bash deploy/debian/setup.sh --check` och `sudo apache2ctl configtest`.
- Skicka aldrig `.env.local`, `deploy/nas/.env`, lösenord, token eller backupinnehåll till publika ärenden.
- Första installation, uppdatering och återställning är skilda flöden. Inget av hjälpskripten raderar en befintlig databas för att ”börja om”.
