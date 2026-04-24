# Konfiguration av e-post i Driftpunkt

Driftpunkt har två huvudbegrepp för e-post:

- `MailServer`
- `SupportMailbox`

Dessutom finns `CompanyMailOverride` för matchning eller överstyrning.

## MailServer

Mailserverobjekt beskriver hur Driftpunkt ska prata med en e-posttjänst.

Servern kan användas för:

- inkommande trafik
- utgående trafik

Konfigurationen omfattar bland annat:

- värdnamn och port
- kryptering
- autentiseringsläge
- riktning
- eventuella OAuth-relaterade uppgifter i de flöden som stödjer det

## SupportMailbox

Supportinkorgar kopplas till en inkommande server och representerar konkreta inkorgar som ska läsas.

De används av:

- spool-baserade flöden
- pollingkommandot `app:mail:poll`

## CompanyMailOverride

Används när ett företag eller viss avsändarklass ska matchas mer exakt än standardlogiken klarar. Det är viktigt i miljöer där:

- flera kunder delar domänliknande adresser
- viss inkorg alltid ska kopplas till visst företag
- okända avsändare ska styras mer kontrollerat

## Test av mailserver

Admin kan trigga test av konfigurerad server via adminportalen. Detta ska användas innan produktionssatt polling.

## Rekommenderad grunduppsättning

1. lägg upp inkommande mailserver
2. lägg upp supportinkorg
3. kör servertest
4. verifiera ingest med en testmail
5. aktivera schemalagd polling

## Utgående e-post

Utgående e-post används bland annat för:

- lösenordsåterställning
- ticketnotifieringar
- bekräftelser vid inkommande ticketflöden

Kontrollera att `mailer`-konfigurationen i Symfony och relevanta systeminställningar stämmer innan skarp drift.
