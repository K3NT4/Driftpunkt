# Testning och kvalitet

Denna version har en aktiv testsvit som framför allt verifierar centrala användar- och driftflöden.

## Kör tester lokalt

```bash
php bin/phpunit
```

## Vad testsviten redan täcker

- adminflöden för identitet, team, innehåll och driftarbete
- mailflöden, inklusive draftgranskning och mailboxpolling
- inkommande mail till tickets och kommentarer
- kund- och teknikerdialog kring tickets
- lösenordsåterställning
- underhållsläge
- startsida, nyheter och systeminställningar
- SLA-kommandon
- databasbackup, restore och jobbkö
- post-update tasks
- releasepaket
- biljettreferenser och `closedAt`-beteende

## Vad testerna betyder för denna version

Testerna visar att Driftpunkt inte bara är ett UI-skal, utan en applikation med verifierade arbetsflöden inom:

- identitet
- ticketing
- mail
- drift
- release

## Nuvarande teststatus i detta snapshot

Vid lokal körning den 19 april 2026 med `php bin/phpunit`:

- 137 tester kördes
- stora delar av sviten passerade
- sviten avslutades inte grönt
- felbilden låg framför allt i `TicketCommentNotificationTest`

Det betyder att kodbasen har bred funktionell täckning, men att hela snapshoten inte bör beskrivas som fullt verifierad releasekandidat utan vidare buggrättning.

## Rekommenderad kvalitetsrutin

Före varje intern release bör vi minst:

1. köra hela testsviten
2. verifiera migrationer
3. verifiera inloggning för minst en kund- och en intern roll
4. testa ett inkommande mailflöde
5. testa backup eller restore i kontrollerad miljö
6. säkerställ att hela testsviten är grön innan extern release
