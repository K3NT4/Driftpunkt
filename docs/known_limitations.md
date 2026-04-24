# Kända begränsningar

Detta är medvetna eller observerade begränsningar som bör kommuniceras tydligt.

## Produktomfång

- inget publikt kundflöde för att skapa helt nytt ticket via webbformulär
- inget externt API för integrationer
- ingen full rapport- eller analysmodul

## Teknik och drift

- lokal standardmiljö och driftmallar bygger på MariaDB
- produktionshardening är ett driftsansvar, inte något appen ensam garanterar
- driftmodellen förutsätter att schemalagda jobb faktiskt sätts upp externt
- hela testsviten var inte grön vid lokal körning den 19 april 2026, framför allt i flöden runt `TicketCommentNotificationTest`

## Process och governance

- draftgranskning innebär manuellt adminarbete i osäkra e-postfall
- release- och uppdateringsflöden finns, men bör fortfarande behandlas som kontrollerad intern process
- dokumentationen beskriver nuvarande version, inte hela framtida roadmapen

## Hur detta ska kommuniceras

Sälj eller intern beställning ska beskriva Driftpunkt som:

- fungerande MVP
- intern produktionsklar efter lokal hardening och driftsättning
- inte färdig som fullskalig enterprise-servicedesk
