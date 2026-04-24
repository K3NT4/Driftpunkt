# Driftpunkt: systembeskrivning för nuvarande version

Detta dokument beskriver den fungerande version som finns i repot just nu.

## Sammanfattning

Driftpunkt är ett webbaserat support- och driftsystem byggt i Symfony 8 med Doctrine och Twig. Systemet kombinerar tre huvuddelar:

- publik webb för information, nyheter, driftstatus och kontakt
- portal för kunder, tekniker och administratörer
- backofficeflöden för inkommande e-post, ticketstyrning, drift, underhåll och releasearbete

## Huvudfunktioner

### Publik webb

- startsida med senaste nyheter, sök och statussammanfattning
- publik kunskapsbas
- nyhetslista och nyhetsdetaljer
- kontaktsida
- publik driftstatussida

### Identitet och inloggning

- roller för privatkund, kund, tekniker, admin och super admin
- separat inloggningsupplevelse per roll via `/login?role=...`
- självregistrering för privatkund om funktionen är aktiverad
- lösenordsåterställning via e-post
- underhållsläge som kan stoppa inloggningar

### Ticketing

- tickets med referens, status, visibility, prioritet, påverkan, request type och SLA
- kundkommentarer och teknikerkommentarer
- notifieringar till kund och intern mottagare vid relevanta händelser
- bilagor i tickets med nedladdning och preview
- arkivering av lokala bilagor till zip för avslutade tickets
- import/export för ärenden i admin

### E-postflöden

- inkommande mail kan läsas från spool eller via polling av supportinkorgar
- mail matchas mot befintligt ticket eller skapar nytt ticket
- okänd eller osäker avsändare kan stoppas i draftgranskning
- admin kan godkänna eller avvisa draftgranskningar
- inkommande bilagor kan förhandsgranskas och flyttas in i ticketflödet

### Kunskapsbas och innehåll

- nyheter med kategorier, publiceringstid och pinnade inlägg
- kunskapsbas med artikel, FAQ och smart tips
- målgrupper för publik, kund eller båda
- admin kan publicera allt
- tekniker kan, beroende på inställningar, bidra i utvalda delar

### Drift och administration

- adminsektioner för identitet, kategorier, automation, inställningar, driftstatus, uppdateringar, jobb, databas, kunskapsbas och SLA
- schemalagt eller aktivt underhållsläge
- statusmonitor med publika statuskort
- databasbackup, restore och optimering
- staging och applicering av koduppdateringspaket
- post-update tasks efter uppdatering
- bakgrundsjobb med loggar och retry

## Teknisk bas

- PHP 8.4+
- Symfony 8
- Doctrine ORM och Doctrine Migrations
- Twig
- Symfony Security
- Symfony Mailer
- MariaDB som standard i lokal miljö och drift

## När vi kallar versionen fungerande

I detta repo betyder "fungerande version" att följande redan finns i kodbasen:

- applikationen startar som en sammanhållen webbprodukt
- databasmodell och migrationer finns
- centrala användarflöden har funktionella tester
- driftkritiska jobb kan köras via kommandon
- installation, uppgradering och drift kan dokumenteras utan att hitta på saknade delar

Versionen är därför fungerande som intern eller kundnära MVP, men inte liktydig med att allt framtida produktomfång är klart. Se `known_limitations.md` för det som fortfarande är medvetet begränsat.
