# Zip-arkivering av ticketbilagor

Driftpunkt kan arkivera lokala bilagor till avslutade tickets i zip-format.

## Syfte

- minska antalet losa filer pa disk
- behalla tillgang till nedladdning och preview
- gora historiska tickets billigare att lagra

## Styrning

Funktionen styrs i admin via installningar for ticketbilagor.

Viktiga installningar:

- om zip-arkivering ar aktiverad
- antal dagar efter stangning innan arkivering ska ske

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

- endast lokala ticketbilagor berors
- fokus ar avslutade tickets, det vill saga `resolved` och `closed` enligt policy och kommandologik
- zip skapas innan originalfiler tas bort
- ticketens bilagelankar fortsatter att fungera via appens lagringslager
- auditlogg kan anvandas for spårbarhet kring arkiveringen

## Schemalaggning

Repoexempel finns i:

- `deploy/systemd/driftpunkt-attachment-archive.service`
- `deploy/systemd/driftpunkt-attachment-archive.timer`
- `deploy/cron/driftpunkt-attachment-archive.cron`

## Rekommendation

- kor nattlig batch om ni vill minimera driftstorning
- anvand `0` dagar bara om ni vet att tekniker inte langre behover originalfilerna i vardagsarbete

## Felsokning

```bash
php bin/console app:archive-ticket-attachments -vvv
```

Kontrollera:

- att funktionen ar aktiverad
- att ticketet verkligen ar avslutat
- att appens lagringsmappar ar skrivbara
- att zip-filerna gar att lasa av webbprocessen
