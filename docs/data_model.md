# Datamodell i nulaget

Detta ar en funktionell oversikt over de viktigaste entiteterna. Dokumentet beskriver ansvar, inte varje kolumn.

## Identitet

### `User`

Anvandare med:

- e-post och namn
- rolltyp
- aktiv/inaktiv-status
- hashat losenord
- MFA-flagga
- notifieringspreferenser
- eventuell koppling till foretag och teknikteam

### `Company`

Foretagsobjekt med:

- namn och primar e-post
- installning for delade tickets
- egen ticketprefix/sekvens nar det behovs

### `TechnicianTeam`

Grupp for tekniker som anvands i tilldelning och samarbete.

### `PasswordResetRequest`

Engangstoken for losenordsaterstallning med giltighetstid och koppling till anvandare.

## Tickets

### `Ticket`

Karnobjektet i systemet. Innehaller bland annat:

- referens
- amne och sammanfattning
- status och visibility
- requester, foretag, ansvarig tekniker och team
- prioritet, paverkan, request type och eskalering
- SLA-koppling
- `closedAt` for definitivt stangda tickets

### `TicketComment`

Kommentar pa ticket med intern eller kundsynlig karaktar.

### `TicketCommentAttachment`

Bilaga kopplad till kommentar, med stod for lagring, nedladdning, preview och senare zip-arkivering.

### `TicketCategory`

Kategori for gruppering, sortering och routing.

### `SlaPolicy`

Svarstider och gransvarden som anvands i SLA-uppfoljning.

### `TicketRoutingRule`

Regler for automatisk eller semiautomatisk styrning av tickets.

### `TicketIntakeField`

Konfigurerbart falt i intakefloden.

### `TicketIntakeTemplate`

Mall/versionerad uppsattning av intakefalt och guidning.

### `TicketImportTemplate`

Mall for importfloden i admin.

### `ImportExportRun`

Loggning av import- och exportkorningar.

### `ExternalTicketImport` och `ExternalTicketEvent`

Stod for importerade arenden och handelser fran externa kallsystem eller filformat.

### `TicketAuditLog`

Intern spårbarhet for viktiga handelser pa ticketnivå.

## Mail

### `MailServer`

Beskriver anslutning och riktning for mailserver, till exempel inkommande eller utgaende.

### `SupportMailbox`

En inkorg som kan pollas eller lasas fran spoolflode.

### `CompanyMailOverride`

Overstyrning som kopplar avsandare eller doman till visst foretag eller mailbeteende.

### `IncomingMail`

Rått eller processat inkommande mail med metadata, processresultat och bilagereferenser.

### `DraftTicketReview`

Adminstyrt granskningsobjekt nar inkommande mail inte kan aktiveras direkt som normalt ticketflode.

## Innehall och kommunikation

### `NewsArticle`

Publik eller portalnara nyhet, inklusive planerat underhall.

### `KnowledgeBaseEntry`

Artikel, FAQ eller smart tips for publik, kund eller bada.

### `NotificationLog`

Logg over notifieringshandelser, mottagare, status och amnesrad.

## System och drift

### `SystemSetting`

Central nyckel-varde-lagring for produktens installningar.

Installningar styr bland annat:

- kundinloggning och sjalvregistrering
- kunskapsbas
- startsida och supportwidget
- kontaktsida
- driftstatus
- underhallsmeddelanden
- SLA-varningar
- ticketbilagepolicy

## Databasstrategi

- SQLite ar standard i lokal miljo
- Doctrine-migrationer beskriver schemautvecklingen
- funktionella tester bygger schema direkt fran metadata
