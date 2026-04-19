# Kundupplevelse i portal och publik webb

## Publik resa

Innan inloggning moter anvandaren:

- startsida
- nyheter
- driftstatus
- kontaktsida
- publik kunskapsbas, om den ar aktiverad
- sok over publik information

Detta gor att Driftpunkt fungerar som bade informationsyta och supportingang.

## Kontoresa

Kunden kan i nulaget:

- logga in
- skapa konto som privatkund om sjalvregistrering ar pa
- begara losenordsaterstallning
- komma till kundportalen efter inloggning

## Kundportalen

Kundportalen ar till for att ge oversikt och dialog, inte for full adminstyrning.

### Kunden kan

- se tickets som kunden har tillgang till
- lasa historik och kommentarer
- skriva nya kommentarer pa tickets kunden ser
- na kundkunskapsbasen om den ar aktiverad

### Kunden kan inte

- administrera andra anvandare
- redigera ticketstruktur, SLA eller interna metadata
- se `internal_only`

## Foretagsdelning

Kunder kopplade till foretag kan fa insyn i `company_shared` tickets inom samma foretag. Detta ar den viktigaste skillnaden mellan privatkund och vanlig kund.

## Forvantad anvandarupplevelse

Systemet ar byggt for att kunden ska kunna:

- fa en snabb bild av lage och driftstatus
- hitta information utan inloggning
- logga in vid behov
- fortsatta dialogen i ticket utan att anvanda separat kanal

## Begransning i nulaget

Det finns inget separat publikt formulardrivet "skapa ticket"-flode for kund i den publika webben. Nytt ticket kommer i praktiken in via interna roller, import eller e-postflode.
