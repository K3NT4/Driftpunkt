# Driftpunkt: systembeskrivning for nuvarande version

Detta dokument beskriver den fungerande version som finns i repot just nu.

## Sammanfattning

Driftpunkt ar ett webbaserat support- och driftsystem byggt i Symfony 8 med Doctrine och Twig. Systemet kombinerar tre huvuddelar:

- publik webb for information, nyheter, driftstatus och kontakt
- portal for kunder, tekniker och administratörer
- backofficefloden for inkommande e-post, ticketstyrning, drift, underhall och releasearbete

## Huvudfunktioner

### Publik webb

- startsida med senaste nyheter, sok och statussammanfattning
- publik kunskapsbas
- nyhetslista och nyhetsdetaljer
- kontaktsida
- publik driftstatussida

### Identitet och inloggning

- roller for privatkund, kund, tekniker, admin och super admin
- separat inloggningsupplevelse per roll via `/login?role=...`
- sjalvregistrering for privatkund om funktionen ar aktiverad
- losenordsaterstallning via e-post
- underhallslage som kan stoppa inloggningar

### Ticketing

- tickets med referens, status, visibility, prioritet, paverkan, request type och SLA
- kundkommentarer och teknikerkommentarer
- notifieringar till kund och intern mottagare vid relevanta handelser
- bilagor i tickets med nedladdning och preview
- arkivering av lokala bilagor till zip for avslutade tickets
- import/export for arenden i admin

### E-postfloden

- inkommande mail kan lasas fran spool eller via polling av supportinkorgar
- mail matchas mot befintligt ticket eller skapar nytt ticket
- okand eller osaker avsandare kan stoppas i draftgranskning
- admin kan godkanna eller avvisa draftgranskningar
- inkommande bilagor kan forhandsgranskas och flyttas in i ticketflodet

### Kunskapsbas och innehall

- nyheter med kategorier, publiceringstid och pinnade inlagg
- kunskapsbas med artikel, FAQ och smart tips
- malgrupper for publik, kund eller bada
- admin kan publicera allt
- tekniker kan, beroende pa installningar, bidra i utvalda delar

### Drift och administration

- adminsektioner for identitet, kategorier, automation, installningar, driftstatus, uppdateringar, jobb, databas, kunskapsbas och SLA
- schemalagt eller aktivt underhallslage
- statusmonitor med publika statuskort
- databasbackup, restore och optimering
- stagning och applicering av koduppdateringspaket
- post-update tasks efter uppdatering
- bakgrundsjobb med loggar och retry

## Teknisk bas

- PHP 8.4+
- Symfony 8
- Doctrine ORM och Doctrine Migrations
- Twig
- Symfony Security
- Symfony Mailer
- SQLite som standard i lokal miljo

## Nar vi kallar versionen fungerande

I detta repo betyder "fungerande version" att foljande redan finns i kodbasen:

- applikationen startar som en sammanhangen webbprodukt
- databasmodell och migrationer finns
- centrala anvandarfloden har funktionella tester
- driftkritiska jobb kan koras via kommandon
- installation, uppgradering och drift kan dokumenteras utan att hitta pa saknade delar

Versionen ar darfor fungerande som intern eller kundnara MVP, men inte liktydig med att allt framtida produktomfang ar klart. Se `known_limitations.md` for det som fortfarande ar medvetet begransat.
