# Produktomfang och MVP

Detta dokument avgransar vad Driftpunkt omfattar i den har versionen.

## Ingår i MVP:n

- publik webb med startsida, nyheter, driftstatus, sok och kontakt
- kundkonto, privatkundsregistrering och aterstallning av losenord
- kundportal for att se och kommentera egna eller foretagsdelade tickets
- teknikerportal for att skapa, ta over, uppdatera och kommentera tickets
- adminportal for identitet, innehall, drift, mail, SLA, databas och uppdateringar
- inkommande mailfloden med spool-ingest, mailboxpolling och draftgranskning
- notifieringslogg och utskick for centrala tickethandelser
- kunskapsbas for publik och/eller kund, styrd av systeminstallningar
- releasepaket for installation och uppgradering

## Ingår inte som fullfardig del

- externt API
- avancerad flerkanalskommunikation utover portal och e-post
- fullstandig multi-tenant isolering pa installationsniva
- integrationer mot externa ITSM- eller CRM-system
- avancerad rapportmotor och BI
- automatiserad provisionering av infrastruktur

## Produktprinciper i nulaget

- Driftpunkt prioriterar tydliga manuella arbetsfloden framfor svartolkad automation.
- Admin styr vad som ar publikt, kundsynligt och internt.
- E-post ar en forstaklassig ingang till ticketsystemet.
- Publik webb och portal ar samma produkt, inte tva separata system.

## Vad som gor MVP:n levererbar

- det finns en sammanhangen rollmodell
- det finns uthalliga ticketfloden for kund, tekniker och admin
- det finns driftfunktioner for backup, underhall, polling och uppdatering
- det finns tester for centrala beteenden
- det finns paketering for nyinstallation och uppgradering

## Rekommenderad positionering

Beskriv denna version som:

"en fungerande MVP for support, driftkommunikation och tickethantering med portal, e-postingest och administrativ driftkontroll"

Beskriv den inte som:

- "enterprise-plattform"
- "fardig fullskalig servicedesk"
- "fardig integrationshub"
