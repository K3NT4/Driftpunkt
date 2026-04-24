# Operativ modell

Detta dokument beskriver hur Driftpunkt drivs som applikation i vardagen.

## Driftsansvar i nuläget

En fungerande installation förutsätter att någon ansvarar för:

- databas och backup
- schemalagda kommandon
- mailpolling eller annat ingestinflöde
- underhållsläge vid uppdatering
- uppföljning av bakgrundsjobb och loggar

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

## Underhållsläge

Underhållsläge kan:

- aktiveras direkt
- schemaläggas
- visas publikt via status/underhållsrelaterade ytor
- blockera inloggningar medan arbete pågår

CLI:

```bash
php bin/console app:maintenance status
php bin/console app:maintenance enable
php bin/console app:maintenance disable
```

## Databasdrift

Admin kan skapa backup, återläsa och optimera databasen via portalen. Databasjobb har separata loggspår och kan köras i bakgrunden.

## Koduppdateringar

Driftpunkt har egen modell för:

- staging av uppdateringspaket
- backup före kodbyte
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

## Minimum för fungerande drift

För att kalla en installation fungerande i drift bör följande vara uppsatt:

- migrerad databas
- minst ett administrativt konto
- fungerande mailkonfiguration om e-postflöden ska användas
- schemaläggning för polling och SLA-kontroll
- rutiner för backup och restore
