# Ticketlivscykel och visibility

## Statusar i den här versionen

- `new`
- `open`
- `pending_customer`
- `resolved`
- `closed`

## Praktisk betydelse

### `new`

Nytt ärende som precis skapats eller importerats.

### `open`

Aktivt ärende hos intern hantering. Det är också den status som normalt återtas när kund svarar.

### `pending_customer`

Ärendet väntar på kundens återkoppling. Detta används bland annat när tekniker skriver kundsynlig kommentar.

### `resolved`

Ärendet bedöms löst men är inte nödvändigtvis definitivt stängt.

### `closed`

Ärendet är definitivt avslutat. `closedAt` sattes när ticketet blir `closed` och rensas om ticketet öppnas igen.

## Visibilitynivåer

- `private`
- `company_shared`
- `internal_only`

### `private`

Synligt för requester och interna roller.

### `company_shared`

Synligt för requester, andra kunder i samma företag och interna roller.

### `internal_only`

Synligt bara för interna roller. Används bland annat för interna tickets och stoppade draftflöden.

## Vanliga övergångar

### Ticket skapas

Kan ske via:

- teknikerportal
- adminflöde
- import
- inkommande e-post

Startstatus är normalt `new` eller `open`.

### Kund kommenterar

- extern kommentar sparas
- status blir normalt `open`
- notifiering kan skickas internt

### Tekniker skriver kundsynlig kommentar

- kundsynlig kommentar sparas
- status blir normalt `pending_customer`
- notifiering kan skickas till requester

### Tekniker eller admin markerar löst

- status blir `resolved`
- ärendet kan fortsatt ha uppföljning eller senare stängas

### Ticket stängs

- status blir `closed`
- `closedAt` sätts
- ticketet kan fortfarande ligga kvar för historik, export och eventuell bilagearkivering

## E-postflöden och lifecycle

Inkommande mail kan ge fyra typer av utfall:

- ny kommentar på befintligt ticket
- nytt aktivt ticket
- nytt ticket i draftgranskning
- avvisat inkommande mail

När draftgranskning avvisas stängs draftticketet och görs `internal_only`.

## Arbetsregler som är viktiga att förstå

- visibility avgör kundsynlighet, inte bara status
- status avgör arbetsläge, inte alltid synlighet
- ett stängt ticket kan fortfarande vara tillgängligt för historik och export
- företagsdelning kräver både rätt relation och rätt visibility
