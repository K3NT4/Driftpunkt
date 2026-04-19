# Operativ modell

Detta dokument beskriver hur Driftpunkt drivs som applikation i vardagen.

## Driftsansvar i nulaget

En fungerande installation forutsatter att nagon ansvarar for:

- databas och backup
- schemalagda kommandon
- mailpolling eller annat ingestinflode
- underhallslage vid uppdatering
- uppfoljning av bakgrundsjobb och loggar

## Viktiga schemalagda jobb

### Mailpolling

```bash
php bin/console app:mail:poll
```

### SLA-kontroll

```bash
php bin/console app:check-ticket-sla
```

### Bilagearkivering

```bash
php bin/console app:archive-ticket-attachments
```

### Eventuella bakgrundsjobb

```bash
php bin/console app:database-maintenance:run <job-id>
php bin/console app:post-update:run <run-id>
```

## Underhallslage

Underhallslage kan:

- aktiveras direkt
- schemalaggas
- visas publikt via status/underhallsrelaterade ytor
- blockera inloggningar medan arbete pagar

CLI:

```bash
php bin/console app:maintenance status
php bin/console app:maintenance enable
php bin/console app:maintenance disable
```

## Databasdrift

Admin kan skapa backup, aterlasa och optimera databasen via portalen. Databasjobb har separata loggspår och kan koras i bakgrunden.

## Koduppdateringar

Driftpunkt har egen modell for:

- staging av uppdateringspaket
- backup fore kodbyte
- applicering av paket
- post-update tasks som exempelvis `composer install`, migrationer och cache clear

## Releasehantering

Releasepaket byggs med:

```bash
php bin/console app:release:build-packages
```

Det skapas:

- installationspaket
- uppgraderingspaket

## Minimum for fungerande drift

For att kalla en installation fungerande i drift bor foljande vara uppsatt:

- migrerad databas
- minst ett administrativt konto
- fungerande mailkonfiguration om e-postfloden ska anvandas
- schemalaggning for polling och SLA-kontroll
- rutiner for backup och restore
