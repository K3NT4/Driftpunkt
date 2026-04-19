# Driftguide for mailbox polling

## Kommando

```bash
php bin/console app:mail:poll
```

Kommandot laser aktiva supportinkorgar och skickar varje hittat meddelande vidare till ingestflodet.

## Nar polling ska anvandas

Polling ska anvandas nar Driftpunkt sjalv ska hamta ny supportpost fran konfigurerade inkorgar.

Det ska inte forvaxlas med:

- `app:mail:ingest`, som behandlar ett enskilt inkommande meddelande
- manuell adminhantering av draftgranskningar

## Forberedelser

Innan polling aktiveras ska foljande vara klart:

- minst en inkommande `MailServer`
- minst en aktiv `SupportMailbox`
- verifierad anslutning mot mailservern
- fungerande utgaende e-post om notifieringar ska skickas

## Schemalaggning

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
2. ersatt sokvagar och anvandare
3. kor:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now driftpunkt-mail-poll.timer
sudo systemctl list-timers | grep driftpunkt-mail-poll
```

## Driftobservationer

Efter aktivering bor du folja upp:

- att nya mail markeras som behandlade
- att tickets eller draftgranskningar faktiskt skapas
- att loggar inte visar upprepade anslutningsfel
- att notifieringar skickas som forvantat

## Felsokning

Kor kommandot manuellt med mer loggning:

```bash
php bin/console app:mail:poll -vvv
```

Kontrollera sedan:

- serveruppgifter
- autentisering
- att inkorgen verkligen ar aktiv
- att databasen och lagringsmappar ar skrivbara
