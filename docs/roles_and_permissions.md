# Roller och behörigheter

## Roller i systemet

- `private_customer`
- `customer`
- `technician`
- `admin`
- `super_admin`

## Övergripande ansvar

### Privatkund

- skapar eget konto via självregistrering om funktionen är aktiverad
- ser sina egna tickets
- kan kommentera sina tickets
- har inte företagsdelning

### Kund

- är normalt kopplad till ett företag
- ser egna tickets
- kan se företagsdelade tickets om visibility tillåter det
- kan kommentera tickets som kunden får se

### Tekniker

- arbetar i teknikerportalen
- kan skapa tickets
- kan ta över och uppdatera tickets inom sina tillåtna arbetsflöden
- kan skriva interna och kundsynliga kommentarer
- kan bidra till kunskapsbasen när inställningar tillåter det

### Admin

- har tillgång till adminportalen
- kan hantera företag, team, användare, mail, innehåll, SLA, driftstatus och vanliga admininställningar
- kan godkänna eller avvisa draftgranskning för inkommande mail

### Super admin

- är en admin med högsta interna behörighet
- används framför allt för första installationen och full administrativ kontroll
- ansvarar för systemunderhåll som underhållsläge, databasjobb, backuper, migreringar, koduppdateringar, post-update tasks och jobbköer

## Ticketvisibility

Ticket kan vara:

- `private`
- `company_shared`
- `internal_only`

Kund kan se ett ticket om:

- kunden är requester
- eller ticketet är `company_shared` och kunden tillhör samma företag

Kund kan inte se:

- `internal_only`
- tickets utan relation till kunden eller kundens företag

## Intern samarbetsmodell

Internt arbete på ticket får göras av:

- admin och super admin
- ansvarig tekniker
- tekniker i samma tilldelade team där flödet tillåter det

Detaljredigering är mer restriktiv än kommentering. Admin har bredare kontroll än tekniker.

## Innehållsstyrning

### Nyheter

- admin kan skapa och uppdatera nyheter
- tekniker har egen nyhetsvy och kan skapa/uppdatera nyheter i teknikerflödet

### Kunskapsbas

- admin kan hantera alla delar
- tekniker kan hantera tillåtna målgrupper enligt systeminställningar
- publik och kundsynlighet styrs av kunskapsbasinställningarna

## Driftstyrning

Följande är superadminansvar:

- underhållsläge
- databashantering
- koduppdateringar och post-update tasks
- jobbköer och loggar

Följande ligger kvar hos admin:

- driftstatuskonfiguration
- mailservrar och supportinkorgar
- innehåll, SLA, identitet och övriga dagliga adminflöden
