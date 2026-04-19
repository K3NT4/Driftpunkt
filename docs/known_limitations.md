# Kanda begransningar

Detta ar medvetna eller observerade begransningar som bor kommuniceras tydligt.

## Produktomfang

- inget publikt kundflode for att skapa helt nytt ticket via webbformular
- inget externt API for integrationer
- ingen full rapport- eller analysmodul

## Teknik och drift

- lokal standardmiljo bygger pa SQLite
- produktionshardening ar ett driftsansvar, inte nagot appen ensam garanterar
- driftmodellen forutsatter att schemalagda jobb faktiskt satts upp externt
- hela testsviten var inte gron vid lokal korning den 19 april 2026, framfor allt i floden runt `TicketCommentNotificationTest`

## Process och governance

- draftgranskning innebar manuellt adminarbete i osakra e-postfall
- release- och uppdateringsfloden finns, men bor fortfarande behandlas som kontrollerad intern process
- dokumentationen beskriver nuvarande version, inte hela framtida roadmapen

## Hur detta ska kommuniceras

Salg eller intern bestallning ska beskriva Driftpunkt som:

- fungerande MVP
- intern produktionsklar efter lokal hardening och driftsattning
- inte fardig som fullskalig enterprise-servicedesk
