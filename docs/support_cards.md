# Supportkort – installation och användning

Supportkort ger ett företag en QR-kod och beständig länk till ett publikt ärendeformulär. Länken ger aldrig inloggning eller åtkomst till befintliga ärenden.

## Förutsättningar

- Kör alla väntande databasmigrationer innan funktionen används, särskilt `Version20261114190000`.
- Installera GD med JPEG, PNG och FreeType i både webb-PHP och schemaläggare.
- Ange en kanonisk publik basadress i `DRIFTPUNKT_PUBLIC_BASE_URL`, normalt med HTTPS. En tom adress tillåter appstart men inte giltig kortexport.
- Bevara `APP_SECRET` vid varje uppgradering. Kortens krypterade länkar och andra sparade hemligheter är beroende av installationens nycklar.

## Behörighet och hantering

Admin tilldelar uttrycklig supportkortsrätt till aktiva kundanvändare i det aktuella företaget. Vanlig ärendeläsrätt eller läsrätt till underbolag ger inte automatiskt denna rätt.

Behöriga användare kan uppdatera kortets text, kontaktuppgifter och valfria logotyp, förhandsvisa senast sparade innehåll samt exportera PDF eller SVG. JPEG/PNG-logotyper valideras och återkodas; godtyckliga externa bilder eller SVG-uppladdningar accepteras inte.

Att spara text behåller länken. **Rotera** ersätter länken och gör gamla utskrifter ogiltiga. **Spärra** stänger den befintliga länken. Företagsavaktivering spärrar kortet permanent; efter återaktivering krävs uttrycklig rotation och nya utskrifter. Återaktivering återställer inte gamla användargrants automatiskt.

## Publik anmälan och granskning

Kända aktiva kunder i rätt företag kan följa det vanliga skapandeflödet. Okända eller avvikande kontakter går till intern koordinatorgranskning. Ogranskade utkast är inte kundsynliga, tilldelas inte och startar inte SLA eller teknikeraviseringar. Inga konton eller e-postalias skapas automatiskt genom QR-länken.

## Backup och loggar

Säkerhetskopiera databas, `APP_SECRET`, privata miljöfiler och `var/support_cards/logos` tillsammans. Nedladdade PDF/SVG-kort innehåller länken och hanteras enligt verksamhetens sekretess- och backuprutin.

Maskera `/supportkort/<token>` i samtliga accessloggar, även befintliga HTTPS-vhostar och externa proxyer. Projektets Apache-exempel och applikationsloggmaskering ersätter inte kontrollen av den egna driftsmiljön. Publicera aldrig en verklig kortlänk i ärenden, dokumentation eller skärmbilder.
