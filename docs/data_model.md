# Datamodell i nuläget

Detta är en funktionell översikt över de viktigaste entiteterna. Dokumentet beskriver ansvar, inte varje kolumn.

## Identitet

### `User`

Användare med:

- e-post och namn
- rolltyp
- aktiv/inaktiv-status
- hashat lösenord
- MFA-flagga
- notifieringspreferenser
- eventuell koppling till företag och teknikteam

### `Company`

Företagsobjekt med:

- namn och primär e-post
- eventuell koppling till moderbolag och dotterbolag
- inställning för delade tickets
- val för om moderbolag får se delade tickets från bolaget
- egen ticketprefix/sekvens när det behövs

### `TechnicianTeam`

Grupp för tekniker som används i tilldelning och samarbete.

### `PasswordResetRequest`

Engångstoken för lösenordsåterställning med giltighetstid och koppling till användare.

## Tickets

### `Ticket`

Kärnobjektet i systemet. Innehåller bland annat:

- referens
- ämne och sammanfattning
- status och visibility
- requester, företag, ansvarig tekniker och team
- prioritet, påverkan, request type och eskalering
- SLA-koppling
- `closedAt` för definitivt stängda tickets

### `TicketComment`

Kommentar på ticket med intern eller kundsynlig karaktär.

### `TicketCommentAttachment`

Bilaga kopplad till kommentar, med stöd för lagring, nedladdning, preview och senare zip-arkivering.

### `TicketCategory`

Kategori för gruppering, sortering och routing.

### `SlaPolicy`

Svarstider och gränsvärden som används i SLA-uppföljning.

### `TicketRoutingRule`

Regler för automatisk eller semiautomatisk styrning av tickets.

### `TicketIntakeField`

Konfigurerbart fält i intakeflöden.

### `TicketIntakeTemplate`

Mall/versionerad uppsättning av intakefält och guidning.

### `TicketImportTemplate`

Mall för importflöden i admin.

### `ImportExportRun`

Loggning av import- och exportkörningar.

### `ExternalTicketImport` och `ExternalTicketEvent`

Stöd för importerade ärenden och händelser från externa källsystem eller filformat.

### `TicketAuditLog`

Intern spårbarhet för viktiga händelser på ticketnivå.

## Mail

### `MailServer`

Beskriver anslutning och riktning för mailserver, till exempel inkommande eller utgående.

### `SupportMailbox`

En inkorg som kan pollas eller läsas från spoolflöde.

### `CompanyMailOverride`

Överstyrning som kopplar avsändare eller domän till visst företag eller mailbeteende.

### `IncomingMail`

Rått eller processat inkommande mail med metadata, processresultat och bilagereferenser.

### `DraftTicketReview`

Adminstyrt granskningsobjekt när inkommande mail inte kan aktiveras direkt som normalt ticketflöde.

## Innehåll och kommunikation

### `NewsArticle`

Publik eller portalnära nyhet, inklusive planerat underhåll.

### `KnowledgeBaseEntry`

Artikel, FAQ eller smart tips för publik, kund eller båda.

### `NotificationLog`

Logg över notifieringshändelser, mottagare, status och ämnesrad.

## System och drift

### `SystemSetting`

Central nyckel-värde-lagring för produktens inställningar.

Inställningar styr bland annat:

- kundinloggning och självregistrering
- kunskapsbas
- startsida och supportwidget
- kontaktsida
- driftstatus
- underhållsmeddelanden
- SLA-varningar
- ticketbilagepolicy

## Databasstrategi

- MariaDB är standard i lokal miljö och drift
- Doctrine-migrationer beskriver schemautvecklingen
- funktionella tester bygger schema direkt från metadata
