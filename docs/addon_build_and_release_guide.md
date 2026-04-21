# Addon Build And Release Guide

Den här guiden beskriver hur addons ska byggas upp för Driftpunkt och hur release-flödet fungerar när bara ägarkontot får släppa addon till ärendesystemet.

## Release-skydd

Addon-release styrs av miljövariabeln `ADDON_RELEASE_OWNER_EMAIL`.

- Sätt den i `.env.local` eller i serverns riktiga miljö.
- Exempel:

```dotenv
ADDON_RELEASE_OWNER_EMAIL=din-adress@example.com
```

- Alla admins kan registrera, konfigurera och verifiera addons.
- Bara användaren vars e-post matchar `ADDON_RELEASE_OWNER_EMAIL` kan trycka på `Släpp addon`.

Om variabeln inte är satt går inga addons att släppa.

## Rekommenderad addonmodell

Varje addon i adminen bör fyllas i som om det vore ett litet releaseobjekt.

- `Namn`: tydligt namn som syns i adminen.
- `Slug`: stabil teknisk identitet, t.ex. `sms-gateway`.
- `Version`: den version som faktiskt är byggd och testad.
- `Installationsstatus`: var addonet ligger i byggkedjan.
- `Health`: aktuell drifthälsa efter verifiering.
- `Senast verifierad`: när du senast bekräftade att addonet fungerar.
- `Beroenden`: externa eller interna system som addonet kräver.
- `Miljövariabler`: allt som måste finnas i driftmiljön.
- `Checklista`: konkreta steg som måste vara klara före release.
- `Adminruta`: valfri intern route om addonet har egen adminyta.
- `Noteringar`: fri text för viktiga release- eller driftanteckningar.

## Byggflöde

Rekommenderat arbetsflöde för varje addon:

1. Registrera addonet i `Admin -> Addons`.
2. Sätt `Installationsstatus` till `Planerad` eller `Konfigureras`.
3. Fyll i beroenden, miljövariabler och checklista innan du börjar koppla in det.
4. Bygg själva addonlogiken i kodbasen.
5. Testa addonet lokalt eller i testmiljö.
6. När det är fungerande: sätt `Installationsstatus` till `Installerad`.
7. Sätt `Health` till `Frisk`, `Varning` eller `Fel`.
8. Sätt `Senast verifierad` till datum/tid för senaste lyckade kontrollen.
9. Gå igenom checklistan och säkerställ att den beskriver det som faktiskt krävdes.
10. Släpp addonet med ägarkontot.

## När ett addon är redo för release

Systemet behandlar ett addon som redo för release när följande stämmer:

- `Installationsstatus` är `Installerad`
- `Health` är `Frisk`
- `Senast verifierad` är satt
- `Checklista` innehåller minst ett steg

Om något av detta saknas får addonet inte släppas till ärendesystemet.

## Rekommenderad kodstruktur

När du bygger ett riktigt addon i koden, håll det samlat och tydligt.

- Lägg domänlogik i en egen modul eller ett tydligt serviceområde under `src/Module/`.
- Undvik att blanda addonlogik direkt in i stora befintliga controllers om det går att kapsla.
- Ha minst ett tydligt entrypoint-lager:
  - controller om addonet har UI
  - service om addonet är integrations- eller bakgrundsdrivet
  - command om addonet körs schemalagt eller manuellt
- Lägg till tester samtidigt som addon byggs, helst funktionella tester om addonet påverkar admin- eller portalflöden.

Exempel på struktur:

```text
src/Module/MyAddon/
  Controller/
  Entity/
  Service/
  Enum/
templates/my_addon/
tests/Functional/MyAddonFlowTest.php
tests/Service/MyAddonServiceTest.php
```

## Zip-format för addonuppladdning

Adminen kan nu ta emot addon som zip-fil och packa upp paketet automatiskt till addonlagret, men bara om paketet följer Driftpunkts struktur.

Minimikrav:

- Paketet måste innehålla `addon.json`
- Paketet måste innehålla en `files/`-mapp
- `files/` måste minst innehålla kod under `src/Module/`
- Övriga tillåtna sökvägar i `files/` är `templates/`, `tests/` och `docs/`

Exempel:

```text
status-board.zip
  status-board/
    addon.json
    files/
      src/Module/StatusBoard/Controller/StatusBoardController.php
      templates/status_board/index.html.twig
      tests/Functional/StatusBoardFlowTest.php
```

Exempel på `addon.json`:

```json
{
  "slug": "status-board",
  "name": "Status Board",
  "description": "Visar statuskort och driftinformation.",
  "version": "1.4.0",
  "files": "files",
  "install_status": "configuring",
  "health_status": "unknown",
  "source_label": "Zip-import",
  "environment_variables": ["STATUS_BOARD_API_KEY"],
  "impact_areas": ["Publik status", "Adminöversikt"]
}
```

När zip-filen godkänns:

- paketet sparas i serverns addonlager
- addonet registreras eller uppdateras automatiskt i `Admin -> Addons`
- version, beroenden, checklista, miljövariabler och påverkade ytor läses in från manifestet
- den uppladdade versionen blir aktiv paketversion i addonlagret

## Aktiv version och rollback

Addon-sektionen skiljer nu på:

- `Aktivt paket`: vilken uppladdad zip-version katalogen just nu följer
- `Release till ärendesystemet`: om addonet är släppt eller inte

Det betyder att du kan:

1. ladda upp version `1.5.0`
2. låta den bli aktiv paketversion i addonlagret
3. se äldre versioner i paketlistan
4. rulla tillbaka till exempelvis `1.4.0` om du behöver

Rollbacken byter aktiv paketversion och uppdaterar addonkatalogens metadata till den valda paketversionen.

Releasehistoriken för addonet ligger kvar separat.

Om paketet inte följer strukturen stoppas uppladdningen direkt.

## Praktisk releasecheck

Innan du släpper ett addon bör du kunna svara ja på allt här:

- Är koden implementerad?
- Är beroenden dokumenterade?
- Är nödvändiga miljövariabler dokumenterade?
- Är checklistan ifylld?
- Är addonet verifierat med datum/tid?
- Är health satt till `Frisk`?
- Är installationsstatus `Installerad`?

Om svaret inte är ja på allt ovan: släpp inte addonet än.

## Rekommenderad rutin för dig som ägare

Eftersom bara ditt konto får släppa addons är det bra att använda samma rutin varje gång:

1. Låt addon byggas och förberedas av dig själv eller andra admins.
2. Läs addonkortet i adminen.
3. Kontrollera checklista, health och verifieringstid.
4. Säkerställ att versionen faktiskt matchar det som är testat.
5. Tryck `Släpp addon`.
6. Notera gärna i `Noteringar` vad som släpptes och varför.

Det gör release-steget till en medveten kontrollpunkt i stället för bara en teknisk toggle.
