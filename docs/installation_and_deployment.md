# Installation och driftsättning

## Lokala förutsättningar

- PHP 8.4 eller senare
- Composer
- MariaDB
- valfritt: Symfony CLI

## Lokal installation

1. installera beroenden

   ```bash
   composer install
   ```

2. initialisera databasen

   För en helt ny MariaDB-installation:

   ```bash
   php bin/console app:install:fresh
   ```

   Kommandot skapar schema direkt från entiteterna, initierar migrationsmetadata och markerar befintliga migrationer som baseline för den nya installationen.
   Reserv-superadmin och ett vanligt admin-konto säkerställs alltid. Standardkonton för tekniker och kund skapas också automatiskt, om du inte anger `--skip-test-accounts`.
   Standardkonton markeras för lösenordsbyte vid första inloggningen.

   Om du i stället arbetar mot en befintlig databas där migrationshistorik redan ska följas:

   ```bash
   php bin/console doctrine:migrations:migrate -n
   ```

3. skapa konto

   ```bash
   php bin/console app:create-test-accounts
   ```

   eller

   ```bash
   php bin/console app:create-admin dinmail@example.com DittLösenord123 Förnamn Efternamn super_admin
   ```

4. starta webbserver

   ```bash
   symfony server:start
   ```

## Docker och lokala hjälptjänster

Repo innehåller `compose.yaml` och `compose.override.yaml` med MariaDB och mailtestning för lokal Docker-baserad utveckling.

## Produktionsdrift

En fungerande driftsättning bör innehålla:

- webbserver som pekar på `public/`
- skrivbar `var/`
- migrerad databas
- reserv-superadmin och minst ett vanligt admin-konto
- process för schemalagda jobb

Om adminytans uppdateringsflöde ska användas för att applicera kodpaket måste PHP/webbserver-användaren, normalt `www-data`, också kunna skriva till koddelarna som byts vid uppdatering: `bin/`, `config/`, `migrations/`, `public/`, `src/`, `templates/`, `composer.json`, `composer.lock` och `symfony.lock`.

På en mer låst produktion kan kodfilerna i stället ägas av `root` och bara `var/` vara skrivbar för `www-data`, men då ska koduppdateringar göras via SSH/deploy-script i stället för via adminytan.

För en konkret Debian-installation, använd:

- `docs/debian_server_setup.md`
- `deploy/debian/app.env.example`
- `deploy/debian/apache-driftpunkt.conf`
- `deploy/debian/setup.sh`

## Rekommenderad efter-installation

1. verifiera inloggning
2. konfigurera kundinloggning och publik kunskapsbas vid behov
3. konfigurera mailservrar och supportinkorgar
4. aktivera polling och SLA-jobb
5. testa backup och restore
6. verifiera driftstatussidan

## Uppgradering

Bygg eller leverera uppgraderingspaket:

```bash
php bin/console app:release:build-packages --type=upgrade
```

Uppgraderingspaket innehåller `vendor/` för att NAS- och Debian-installationer inte ska behöva hämta PHP-paket från internet mitt i en uppdatering.

Rekommenderad ordning i drift:

1. skapa backup
2. aktivera underhållsläge
3. stagea/applicera uppdateringspaket
4. låt uppdateringsflödet köra Composer, MariaDB-migrationer och cache-rensning
5. verifiera inloggning, tickets och mail
6. avaktivera underhållsläge
