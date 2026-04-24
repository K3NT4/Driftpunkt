# Kundupplevelse i portal och publik webb

## Publik resa

Innan inloggning möter användaren:

- startsida
- nyheter
- driftstatus
- kontaktsida
- publik kunskapsbas, om den är aktiverad
- sök över publik information

Detta gör att Driftpunkt fungerar som både informationsyta och supportingång.

## Kontoresa

Kunden kan i nuläget:

- logga in
- skapa konto som privatkund om självregistrering är på
- begära lösenordsåterställning
- komma till kundportalen efter inloggning

## Kundportalen

Kundportalen är till för att ge översikt och dialog, inte för full adminstyrning.

### Kunden kan

- se tickets som kunden har tillgång till
- läsa historik och kommentarer
- skriva nya kommentarer på tickets kunden ser
- nå kundkunskapsbasen om den är aktiverad

### Kunden kan inte

- administrera andra användare
- redigera ticketstruktur, SLA eller interna metadata
- se `internal_only`

## Företagsdelning

Kunder kopplade till företag kan få insyn i `company_shared` tickets inom samma företag. Detta är den viktigaste skillnaden mellan privatkund och vanlig kund.

## Förväntad användarupplevelse

Systemet är byggt för att kunden ska kunna:

- få en snabb bild av läge och driftstatus
- hitta information utan inloggning
- logga in vid behov
- fortsätta dialogen i ticket utan att använda separat kanal

## Begränsning i nuläget

Det finns inget separat publikt formulärdrivet "skapa ticket"-flöde för kund i den publika webben. Nytt ticket kommer i praktiken in via interna roller, import eller e-postflöde.
