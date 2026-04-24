# Driftguide för mailbox polling

## Kommando

```bash
php bin/console app:mail:poll
```

Kommandot läser aktiva supportinkorgar och skickar varje hittat meddelande vidare till ingestflödet.

## När polling ska användas

Polling ska användas när Driftpunkt själv ska hämta ny supportpost från konfigurerade inkorgar.

Det ska inte förväxlas med:

- `app:mail:ingest`, som behandlar ett enskilt inkommande meddelande
- manuell adminhantering av draftgranskningar

## Förberedelser

Innan polling aktiveras ska följande vara klart:

- minst en inkommande `MailServer`
- minst en aktiv `SupportMailbox`
- verifierad anslutning mot mailservern
- fungerande utgående e-post om notifieringar ska skickas

## Schemaläggning

Repoexempel finns i:

- `deploy/systemd/driftpunkt-mail-poll.service`
- `deploy/systemd/driftpunkt-mail-poll.timer`
- `deploy/cron/driftpunkt-mail-poll.cron`

## Rekommenderad cron

```cron
*/5 * * * * cd /path/to/driftpunkt && php bin/console app:mail:poll --env=prod >> var/log/mail-poll.log 2>&1
```

## Rekommenderad systemd-timer

1. kopiera mallfilerna till `/etc/systemd/system/`
2. ersätt sökvägar och användare
3. kör:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now driftpunkt-mail-poll.timer
sudo systemctl list-timers | grep driftpunkt-mail-poll
```

## Driftobservationer

Efter aktivering bör du följa upp:

- att nya mail markeras som behandlade
- att tickets eller draftgranskningar faktiskt skapas
- att loggar inte visar upprepade anslutningsfel
- att notifieringar skickas som förväntat

## Felsökning

Kör kommandot manuellt med mer loggning:

```bash
php bin/console app:mail:poll -vvv
```

Kontrollera sedan:

- serveruppgifter
- autentisering
- att inkorgen verkligen är aktiv
- att databasen och lagringsmappar är skrivbara
