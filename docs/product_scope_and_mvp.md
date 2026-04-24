# Produktomfång och MVP

Detta dokument avgränsar vad Driftpunkt omfattar i den här versionen.

## Ingår i MVP:n

- publik webb med startsida, nyheter, driftstatus, sök och kontakt
- kundkonto, privatkundsregistrering och återställning av lösenord
- kundportal för att se och kommentera egna eller företagsdelade tickets
- teknikerportal för att skapa, ta över, uppdatera och kommentera tickets
- adminportal för identitet, innehåll, drift, mail, SLA, databas och uppdateringar
- inkommande mailflöden med spool-ingest, mailboxpolling och draftgranskning
- notifieringslogg och utskick för centrala tickethändelser
- kunskapsbas för publik och/eller kund, styrd av systeminställningar
- releasepaket för installation och uppgradering

## Ingår inte som fullfärdig del

- externt API
- avancerad flerkanalskommunikation utöver portal och e-post
- fullständig multi-tenant isolering på installationsnivå
- integrationer mot externa ITSM- eller CRM-system
- avancerad rapportmotor och BI
- automatiserad provisionering av infrastruktur

## Produktprinciper i nuläget

- Driftpunkt prioriterar tydliga manuella arbetsflöden framför svårtolkad automation.
- Admin styr vad som är publikt, kundsynligt och internt.
- E-post är en förstaklassig ingång till ticketsystemet.
- Publik webb och portal är samma produkt, inte två separata system.

## Vad som gör MVP:n levererbar

- det finns en sammanhållen rollmodell
- det finns uthålliga ticketflöden för kund, tekniker och admin
- det finns driftfunktioner för backup, underhåll, polling och uppdatering
- det finns tester för centrala beteenden
- det finns paketering för nyinstallation och uppgradering

## Rekommenderad positionering

Beskriv denna version som:

"en fungerande MVP för support, driftkommunikation och tickethantering med portal, e-postingest och administrativ driftkontroll"

Beskriv den inte som:

- "enterprise-plattform"
- "färdig fullskalig servicedesk"
- "färdig integrationshub"
