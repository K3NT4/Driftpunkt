# Installation och driftsattning

## Lokala forutsattningar

- PHP 8.4 eller senare
- Composer
- SQLite eller annan konfigurerad databas
- valfritt: Symfony CLI

## Lokal installation

1. installera beroenden

   ```bash
   composer install
   ```

2. kor migrationer

   ```bash
   php bin/console doctrine:migrations:migrate -n
   ```

3. skapa konto

   ```bash
   php bin/console app:create-test-accounts
   ```

   eller

   ```bash
   php bin/console app:create-admin dinmail@example.com DittLosenord123 Fornamn Efternamn super_admin
   ```

4. starta webbserver

   ```bash
   symfony server:start
   ```

## Docker och lokala hjalptjanster

Repo innehaller `compose.yaml` och `compose.override.yaml`. Dessa ar framst anvandbara for hjalptjanster i utveckling, till exempel mailtestning.

## Produktionsdrift

En fungerande driftsattning bor innehalla:

- webbserver som pekar pa `public/`
- skrivbar `var/`
- migrerad databas
- minst ett admin- eller superadminkonto
- process for schemalagda jobb

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

Rekommenderad ordning i drift:

1. skapa backup
2. aktivera underhallslage
3. stagea/applicera uppdateringspaket
4. kor post-update tasks
5. verifiera inloggning, tickets och mail
6. avaktivera underhallslage
