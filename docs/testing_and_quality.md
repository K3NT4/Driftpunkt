# Testning och kvalitet

Denna version har en aktiv testsvit som framfor allt verifierar centrala anvandar- och driftfloden.

## Kor tester lokalt

```bash
php bin/phpunit
```

## Vad testsviten redan tacker

- adminfloden for identitet, team, innehall och driftarbete
- mailfloden, inklusive draftgranskning och mailboxpolling
- inkommande mail till tickets och kommentarer
- kund- och teknikerdialog kring tickets
- losenordsaterstallning
- underhallslage
- startsida, nyheter och systeminstallningar
- SLA-kommandon
- databasbackup, restore och jobbko
- post-update tasks
- releasepaket
- biljettreferenser och `closedAt`-beteende

## Vad testerna betyder for denna version

Testerna visar att Driftpunkt inte bara ar ett UI-skal, utan en applikation med verifierade arbetsfloden inom:

- identitet
- ticketing
- mail
- drift
- release

## Nuvarande teststatus i detta snapshot

Vid lokal korning den 19 april 2026 med `php bin/phpunit`:

- 137 tester korades
- stora delar av sviten passerade
- sviten avslutades inte gront
- felbilden lag framfor allt i `TicketCommentNotificationTest`

Det betyder att kodbasen har bred funktionell tackning, men att hela snapshoten inte bor beskrivas som fullt verifierad releasekandidat utan vidare buggrattning.

## Rekommenderad kvalitetsrutin

Fore varje intern release bor vi minst:

1. kora hela testsviten
2. verifiera migrationer
3. verifiera inloggning for minst en kund- och en intern roll
4. testa ett inkommande mailflode
5. testa backup eller restore i kontrollerad miljo
6. sakerstall att hela testsviten ar gron innan extern release
