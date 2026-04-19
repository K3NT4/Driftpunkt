# Konfiguration av e-post i Driftpunkt

Driftpunkt har tva huvudbegrepp for e-post:

- `MailServer`
- `SupportMailbox`

Dessutom finns `CompanyMailOverride` for matchning eller overstyrning.

## MailServer

Mailserverobjekt beskriver hur Driftpunkt ska prata med en e-posttjanst.

Servern kan anvandas for:

- inkommande trafik
- utgaende trafik

Konfigurationen omfattar bland annat:

- vardnamn och port
- kryptering
- autentiseringslage
- riktning
- eventuella OAuth-relaterade uppgifter i de floden som stodjer det

## SupportMailbox

Supportinkorgar kopplas till en inkommande server och representerar konkreta inkorgar som ska lasas.

De anvands av:

- spool-baserade floden
- pollingkommandot `app:mail:poll`

## CompanyMailOverride

Anvands nar ett foretag eller viss avsandarklass ska matchas mer exakt an standardlogiken klarar. Det ar viktigt i miljoer dar:

- flera kunder delar domanliknande adresser
- viss inkorg alltid ska kopplas till visst foretag
- okanda avsandare ska styras mer kontrollerat

## Test av mailserver

Admin kan trigga test av konfigurerad server via adminportalen. Detta ska anvandas innan produktionssatt polling.

## Rekommenderad grunduppsattning

1. lagg upp inkommande mailserver
2. lagg upp supportinkorg
3. kor servertest
4. verifiera ingest med en testmail
5. aktivera schemalagd polling

## Utgaende e-post

Utgaende e-post anvands bland annat for:

- losenordsaterstallning
- ticketnotifieringar
- bekräftelser vid inkommande ticketfloden

Kontrollera att `mailer`-konfigurationen i Symfony och relevanta systeminstallningar stammer innan skarp drift.
