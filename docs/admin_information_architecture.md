# Adminportalens informationsarkitektur

Adminportalen är uppdelad i sektioner som speglar verkliga drift- och administrationsuppgifter.

## Sektioner

### `overview`

Samlad översikt över läget i systemet.

### `categories`

Hantera ticketkategorier, intakefält, intakemallar och routingregler.

### `automation`

Fokus på styrning av ticketflöden, routing och tillhörande mallsteg.

### `settings`

Produktinställningar för funktioner och beteenden.

### `settings-content`

Inställningar för innehållsnära delar som startsida, kontakt, kunskapsbas och underhållsmeddelanden.

### `status`

Konfiguration av driftstatussida och statussignaler.

### `updates`

Koduppdateringspaket, post-update tasks och tillhörande backupflöden. Kräver `super_admin`.

### `jobs`

Bakgrundsjobb, loggning, retry och städning av avslutade jobb. Kräver `super_admin`.

### `addons`

Plats för tillaggs- och expansionsnära administration.

### `database`

Backup, restore, optimering och nedladdning av databaskopior. Kräver `super_admin`.

### `knowledge-base`

Kunskapsbasinlägg och relaterade inställningar.

### `sla`

SLA-policys och varningsinställningar.

### `logs`

Notifieringsloggar och historiknära spårbarhet.

## Övriga adminvyer utanför huvudsektionerna

- `identity`
- `mail`
- `nyheter`
- `import/export`

De ligger som egna flöden eftersom de har egna arbetsytor och fler formularkombinationer.

## Principer bakom strukturen

- identitet och mail har egna vyer för att de är operativa ansvarsområden
- databasen, uppdateringar och jobb är separata superadminytor för att minska risk vid driftarbete
- innehåll och driftstatus hålls separata eftersom de riktar sig till olika mottagare
- jobb och loggar finns som egna ytor för felsökning och uppföljning
