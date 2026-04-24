# Regler för behandling av inkommande mail

Detta dokument beskriver hur inkommande mail hanteras i den här versionen.

## Huvudutfall

Ett inkommande mail kan resultera i:

- kommentar på befintligt ticket
- nytt aktivt ticket
- draftgranskning för admin
- avvisning

## Typiska beslutspunkter

### Känner systemet igen ticketreferens?

Om ja kan mailet kopplas som fortsatt dialog på befintligt ärende.

### Känner systemet igen avsändaren?

Om avsändaren kan matchas mot känd kund eller företag är chansen högre att mailet kan bli ett aktivt ticket direkt.

### Finns tillräcklig kontext för trygg aktivering?

Om svaret är nej stoppas mailet i draftgranskning i admin istället för att skapa osäker extern synlighet.

## Draftgranskning

Draftgranskning används för att skydda systemet mot felaktig automatisk ticketaktivering.

Admin kan:

- granska avsändare, bolag, tilldelning och visibility
- förhandsvisa bilagor
- godkänna och aktivera ticket
- avvisa och stänga draftticketet

Vid godkännande kan bilagor följa med in i ticketet. Vid avvisning blir ticketet internt och stängs.

## Bilagor

Inkommande bilagor lagras separat i ingestflödet och kan:

- förhandsgranskas av admin
- laddas ner
- kopieras in i ticket vid godkännande av draft

## Avvisning

Mail kan avvisas när:

- avsändaren inte bör skapa ärenden automatiskt
- matchning inte är tillräckligt säker
- admin uttryckligen stoppar draftgranskningen

## Viktig princip

Mailflödet är byggt för kontrollerad ingest, inte blind automatisk skapning. När osäkerhet finns väljer systemet hellre intern granskning än risk för felaktig kundsynlighet.
