# Roller och behorigheter

## Roller i systemet

- `private_customer`
- `customer`
- `technician`
- `admin`
- `super_admin`

## Overgripande ansvar

### Privatkund

- skapar eget konto via sjalvregistrering om funktionen ar aktiverad
- ser sina egna tickets
- kan kommentera sina tickets
- har inte foretagsdelning

### Kund

- ar normalt kopplad till ett foretag
- ser egna tickets
- kan se foretagsdelade tickets om visibility tillater det
- kan kommentera tickets som kunden far se

### Tekniker

- arbetar i teknikerportalen
- kan skapa tickets
- kan ta over och uppdatera tickets inom sina tillatna arbetsfloden
- kan skriva interna och kundsynliga kommentarer
- kan bidra till kunskapsbasen nar installningar tillater det

### Admin

- har tillgang till adminportalen
- kan hantera foretag, team, anvandare, mail, innehall, SLA och driftsektioner
- kan godkanna eller avvisa draftgranskning for inkommande mail
- kan koa databasjobb och uppdateringsjobb

### Super admin

- ar en admin med hogsta interna behorighet
- anvands framfor allt for forsta installationen och full administrativ kontroll

## Ticketvisibility

Ticket kan vara:

- `private`
- `company_shared`
- `internal_only`

Kund kan se ett ticket om:

- kunden ar requester
- eller ticketet ar `company_shared` och kunden tillhor samma foretag

Kund kan inte se:

- `internal_only`
- tickets utan relation till kunden eller kundens foretag

## Intern samarbetsmodell

Internt arbete pa ticket far goras av:

- admin och super admin
- ansvarig tekniker
- tekniker i samma tilldelade team dar flodet tillater det

Detaljredigering ar mer restriktiv an kommentering. Admin har bredare kontroll an tekniker.

## Innehallsstyrning

### Nyheter

- admin kan skapa och uppdatera nyheter
- tekniker har egen nyhetsvy och kan skapa/uppdatera nyheter i teknikerflodet

### Kunskapsbas

- admin kan hantera alla delar
- tekniker kan hantera tillatna malgrupper enligt systeminstallningar
- publik och kundsynlighet styrs av kunskapsbasinstallningarna

## Driftstyrning

Foljande ar adminansvar i nulaget:

- underhallslage
- driftstatuskonfiguration
- mailservrar och supportinkorgar
- databashantering
- koduppdateringar och post-update tasks
- jobbkoer och loggar
