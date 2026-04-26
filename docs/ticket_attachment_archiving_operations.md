# Zip-arkivering av ticketbilagor

Driftpunkt kan arkivera lokala bilagor till avslutade tickets i zip-format.

## Syfte

- minska antalet lösa filer på disk
- behålla tillgång till nedladdning och preview
- göra historiska tickets billigare att lagra

## Styrning

Funktionen styrs i admin via inställningar för ticketbilagor.

Viktiga inställningar:

- om zip-arkivering är aktiverad
- antal dagar efter stängning innan arkivering ska ske

## Kommando

```bash
php bin/console app:archive-ticket-attachments
```

Vanliga flaggor:

```bash
php bin/console app:archive-ticket-attachments --days=0
php bin/console app:archive-ticket-attachments --force
```

## Beteende

- endast lokala ticketbilagor berörs
- fokus är avslutade tickets, det vill säga `resolved` och `closed` enligt policy och kommandologik
- zip skapas innan originalfiler tas bort
- ticketens bilagelänkar fortsätter att fungera via appens lagringslager
- auditlogg kan användas för spårbarhet kring arkiveringen

## Schemaläggning

Repoexempel finns i:

- `deploy/systemd/driftpunkt-attachment-archive.service`
- `deploy/systemd/driftpunkt-attachment-archive.timer`
- `deploy/cron/driftpunkt-attachment-archive.cron`

## Rekommendation

- kör nattlig batch om ni vill minimera driftstörning
- använd `0` dagar bara om ni vet att tekniker inte längre behöver originalfilerna i vardagsarbete
- systemd-mallen skriver stdout och stderr till `var/log/ticket-attachment-archive.log`

## Felsökning

```bash
php bin/console app:archive-ticket-attachments -vvv
```

Kontrollera:

- att funktionen är aktiverad
- att ticketet verkligen är avslutat
- att appens lagringsmappar är skrivbara
- att zip-filerna går att läsa av webbprocessen
