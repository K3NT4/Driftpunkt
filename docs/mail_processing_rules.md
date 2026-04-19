# Regler for behandling av inkommande mail

Detta dokument beskriver hur inkommande mail hanteras i den har versionen.

## Huvudutfall

Ett inkommande mail kan resultera i:

- kommentar pa befintligt ticket
- nytt aktivt ticket
- draftgranskning for admin
- avvisning

## Typiska beslutspunkter

### Kanner systemet igen ticketreferens?

Om ja kan mailet kopplas som fortsatt dialog pa befintligt arende.

### Kanner systemet igen avsandaren?

Om avsandaren kan matchas mot känd kund eller foretag ar chansen hogre att mailet kan bli ett aktivt ticket direkt.

### Finns tillracklig kontext for trygg aktivering?

Om svaret ar nej stoppas mailet i draftgranskning i admin istallet for att skapa osaker extern synlighet.

## Draftgranskning

Draftgranskning anvands for att skydda systemet mot felaktig automatisk ticketaktivering.

Admin kan:

- granska avsandare, bolag, tilldelning och visibility
- forhåndsvisa bilagor
- godkanna och aktivera ticket
- avvisa och stanga draftticketet

Vid godkannande kan bilagor folja med in i ticketet. Vid avvisning blir ticketet internt och stangs.

## Bilagor

Inkommande bilagor lagras separat i ingestflodet och kan:

- forhandsgranskas av admin
- laddas ner
- kopieras in i ticket vid godkannande av draft

## Avvisning

Mail kan avvisas nar:

- avsandaren inte bor skapa arenden automatiskt
- matchning inte ar tillrackligt saker
- admin uttryckligen stoppar draftgranskningen

## Viktig princip

Mailflodet ar byggt for kontrollerad ingest, inte blind automatisk skapning. Nar osakerhet finns valjer systemet hellre intern granskning an risk for felaktig kundsynlighet.
