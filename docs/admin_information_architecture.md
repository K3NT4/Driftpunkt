# Adminportalens informationsarkitektur

Adminportalen ar uppdelad i sektioner som speglar verkliga drift- och administrationsuppgifter.

## Sektioner

### `overview`

Samlad oversikt over laget i systemet.

### `categories`

Hantera ticketkategorier, intakefalt, intakemallar och routingregler.

### `automation`

Fokus pa styrning av ticketfloden, routing och tillhorande mallsteg.

### `settings`

Produktinstallningar for funktioner och beteenden.

### `settings-content`

Installningar for innehallsnara delar som startsida, kontakt, kunskapsbas och underhallsmeddelanden.

### `status`

Konfiguration av driftstatussida och statussignaler.

### `updates`

Koduppdateringspaket, post-update tasks och tillhorande backupfloden.

### `jobs`

Bakgrundsjobb, loggning, retry och städning av avslutade jobb.

### `addons`

Plats for tillaggs- och expansionsnara administration.

### `database`

Backup, restore, optimering och nedladdning av databaskopior.

### `knowledge-base`

Kunskapsbasinlagg och relaterade installningar.

### `sla`

SLA-policys och varningsinstallningar.

### `logs`

Notifieringsloggar och historiknara spårbarhet.

## Ovriga adminvyer utanfor huvudsektionerna

- `identity`
- `mail`
- `nyheter`
- `import/export`

De ligger som egna floden eftersom de har egna arbetsytor och fler formularkombinationer.

## Principer bakom strukturen

- identitet och mail har egna vyer for att de ar operativa ansvarsomraden
- databasen och uppdateringar ar separata for att minska risk vid driftarbete
- innehall och driftstatus halls separata eftersom de riktar sig till olika mottagare
- jobb och loggar finns som egna ytor for felsokning och uppfoljning
