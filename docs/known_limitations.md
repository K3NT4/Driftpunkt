# Kända begränsningar

Detta är medvetna eller observerade begränsningar som bör kommuniceras tydligt.

## Produktomfång

- inget externt API för integrationer
- ingen full fristående BI-motor med sparade egna rapportmallar eller externa BI-integrationer

## Teknik och drift

- lokal standardmiljö och driftmallar bygger på MariaDB
- extern produktionshardening som TLS, filägarskap, brandvägg, patchning och backup är fortfarande driftsansvar, även om appen sätter grundläggande HTTP-säkerhetsheaders själv
- systemd, cron eller Docker-scheduler rekommenderas för förutsägbar produktion
- Driftpunkt kan ha intern fallback för vissa bakgrundsjobb, men produktion ska ändå ha en extern scheduler för mailpolling, SLA-kontroll, bilagearkivering, månadsrapporter och köade koduppdateringar
- adminytans koduppdateringar kräver att servern kan skriva till de koddelar som uppdateringspaketet byter, alternativt att uppdateringar görs via SSH/deploy-script
- köade koduppdateringar kan bli liggande som `queued` om ingen cron/systemd-worker kör `app:code-update:apply-run`
- Docker/NAS-miljöer kan ha begränsningar i cron, filrättigheter och bakgrundsprocesser; därför ska en separat cron-worker eller NAS-schemaläggning verifieras efter installation
- vissa miljöberoende testflöden kan kräva lokalt uppdaterad testdatabas och mail-/filinställningar innan hela sviten körs

## Process och governance

- draftgranskning innebär manuellt adminarbete i osäkra e-postfall
- release- och uppdateringsflöden finns, men bör fortfarande behandlas som kontrollerad intern process
- uppdateringar via adminytan bör kompletteras med backup, underhållsläge, loggkontroll och verifiering efter genomförd uppdatering
- dokumentationen beskriver nuvarande version, inte hela framtida roadmapen

## Hur detta ska kommuniceras

Sälj eller intern beställning ska beskriva Driftpunkt som:

- fungerande MVP
- intern produktionsklar efter lokal hardening och driftsättning
- inte färdig som fullskalig enterprise-servicedesk

När Driftpunkt installeras i kundnära miljö ska det vara tydligt att produktionsansvar omfattar:

- korrekt serverhardening
- fungerande backup/restore
- fungerande cron eller systemd timers
- verifierad update-worker för köade koduppdateringar om adminytans uppdateringsfunktion används
