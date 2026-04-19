# Ticketlivscykel och visibility

## Statusar i den har versionen

- `new`
- `open`
- `pending_customer`
- `resolved`
- `closed`

## Praktisk betydelse

### `new`

Nytt arende som precis skapats eller importerats.

### `open`

Aktivt arende hos intern hantering. Det ar ocksa den status som normalt atertas nar kund svarar.

### `pending_customer`

Arendet vantar pa kundens aterkoppling. Detta anvands bland annat nar tekniker skriver kundsynlig kommentar.

### `resolved`

Arendet bedoms lost men ar inte nodvandigtvis definitivt stangt.

### `closed`

Arendet ar definitivt avslutat. `closedAt` sattes nar ticketet blir `closed` och rensas om ticketet oppnas igen.

## Visibilitynivaer

- `private`
- `company_shared`
- `internal_only`

### `private`

Synligt for requester och interna roller.

### `company_shared`

Synligt for requester, andra kunder i samma foretag och interna roller.

### `internal_only`

Synligt bara for interna roller. Anvands bland annat for interna tickets och stoppade draftfloden.

## Vanliga overgangar

### Ticket skapas

Kan ske via:

- teknikerportal
- adminflode
- import
- inkommande e-post

Startstatus ar normalt `new` eller `open`.

### Kund kommenterar

- extern kommentar sparas
- status blir normalt `open`
- notifiering kan skickas internt

### Tekniker skriver kundsynlig kommentar

- kundsynlig kommentar sparas
- status blir normalt `pending_customer`
- notifiering kan skickas till requester

### Tekniker eller admin markerar lost

- status blir `resolved`
- arendet kan fortsatt ha uppfoljning eller senare stangas

### Ticket stangs

- status blir `closed`
- `closedAt` satts
- ticketet kan fortfarande ligga kvar for historik, export och eventuell bilagearkivering

## E-postfloden och lifecycle

Inkommande mail kan ge fyra typer av utfall:

- ny kommentar pa befintligt ticket
- nytt aktivt ticket
- nytt ticket i draftgranskning
- avvisat inkommande mail

Nar draftgranskning avvisas stangs draftticketet och goras `internal_only`.

## Arbetsregler som ar viktiga att forsta

- visibility avgor kundsynlighet, inte bara status
- status avgor arbetslage, inte alltid synlighet
- ett stangt ticket kan fortfarande vara tillgangligt for historik och export
- foretagsdelning kraver bade ratt relation och ratt visibility
