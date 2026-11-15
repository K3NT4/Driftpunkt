# Uppgradering till Driftpunkt 2.0.0 – förbered liveservern

Detta är driftanvisningar, inte ett intyg om att kontrollerna har utförts på din liveserver. NAS-prov och kvarvarande acceptans redovisas separat. Utför först en repetition på en isolerad kopia med spärrade utskick och integrationer.

## Före uppdateringen

1. Inventera nuvarande version, PHP för webb och bakgrundsjobb, databas, aktiva tillägg, webbserver/proxy, Compose-filer och beständiga volymer. Spara konfigurationen skyddat. Lämna inte ut hemligheter i loggar eller skärmbilder.
2. Ta en samordnad säkerhetskopia av databas, programfiler, privata miljöfiler, krypteringsnycklar, bilagor, uppladdade logotyper och övriga beständiga volymer. Bevara `APP_SECRET` och eventuella separata MFA-/mejlnycklar. Testa återställning i isolerad miljö. Databasbackup ensam räcker inte. Supportkortens länkar är beroende av bevarad nyckel och databas.
3. Kontrollera ledigt diskutrymme för paket, uppackning, kodbackup, databasbackup och Composer/cache. Förkontrollen i superadmin måste vara grön, inklusive skrivrättighet till exempelvis `.dockerignore`. Kontrollera faktisk webbserveranvändare; använd inte generell `chmod 777` eller rekursiv ägarändring över databasvolymer.
4. Kontrollera paketets PHP-krav: 64-bitars PHP enligt `composer.json`, nödvändiga extensions och särskilt **GD med JPEG/PNG** i både app och schemaläggare. Docker-avbildningen behöver byggas om om GD saknas; ett ZIP-paket installerar inte PHP-extensions. Kör `composer check-platform-reqs --no-dev` med installationens Composer och PHP.
5. Hämta **upgrade-paketet**, inte installationspaketet, från den officiella releasen. Jämför SHA-256 med den betrodda releasekällan. Kör aldrig färskinstallation, testsvit eller fixtures mot befintlig produktionsdatabas.
6. Planera underhållsfönster. Pausa mejlintag och verksamhetsjobb under kod-/schemaändringarna. Behåll den process som faktiskt utför den köade koduppdateringen tillgänglig. Kontrollera att bakgrundsstart från webbprocessen fungerar innan du köar; en vanlig mejl-/SLA-schemaläggare är inte automatiskt en konsument för koduppdateringar.

### Docker: kontrollera hur programkoden delas

Identifiera Compose-konfigurationen och volymerna innan något återskapas. Använd rätt containernamn:

```bash
docker inspect <app-container> --format '{{index .Config.Labels "com.docker.compose.project.config_files"}}'
docker inspect <app-container> --format '{{range .Mounts}}{{println .Source "->" .Destination}}{{end}}'
```

Om hela programkatalogen delas mellan app och schemaläggare kan webbens koduppdatering ändra samma filer för båda. Om koden i stället ligger **inne i Docker-avbildningen** måste båda tjänsterna få en avbildning byggd från samma nya version. En uppdatering inne i appcontainern uppdaterar inte avbildningen eller schemaläggarens kod och kan försvinna vid återskapande. Välj ett konsekvent driftsätt; bygg inte om från en gammal källkatalog efter en webbuppdatering. Projektets `deploy/nas/compose.yaml` är avbildningsbaserad.

## Aktivera valet mellan klassisk och ny personallayout

Valet är **avstängt som standard**. Superadmin kan aktivera det direkt i webbgränssnittet:

1. Öppna **Inställningar** och sök efter **Portalens layout** (även ”klassisk”, ”tekniker” eller ”layout” fungerar).
2. Öppna träffen och markera **Låt tekniker och ärendekoordinatorer välja klassisk eller ny layout**.
3. Klicka på **Spara layoutinställning**. Ändringen gäller direkt och kräver ingen containeromstart eller databasändring utöver den vanliga systeminställningen.

Reglaget visas endast för superadmin; vanlig admin kan inte ändra det genom direktanrop heller. Avstängning använder den moderna layouten utan att radera personalens sparade layout-/färgval. Återaktivering återanvänder giltigt kvitterade val; annars visas introduktionen. Kontrollera båda layouterna i avsedd miljö före bred aktivering.

För installationer som redan använder `DRIFTPUNKT_INTERNAL_LAYOUT_CHOICE_ENABLED` behålls den som **standardvärde när inget val har sparats i superadmin**. Det sparade webbvalet har företräde för både på och av. En miljöändring skriver alltså inte över superadmins beslut. Drift via Docker `env_file` kräver återskapande av berörda containrar endast när själva containermiljön ändras; reglaget i webbgränssnittet kräver inte det.

### Kontrollera att valet fungerar

- Logga in med ett riktigt **tekniker- respektive ärendekoordinatorkonto**. Admin/superadmin får inte valet enbart genom rollarv.
- Vid första besöket visas introduktionen för layoutval. Välj **Ny** eller **Klassisk** och spara. Utan giltig kvittens används klassisk layout när funktionen är aktiverad.
- Öppna **Inställningar** och avsnittet **Personlig layout**. Byt, spara och ladda om. Kontrollera också att valet finns kvar efter ny inloggning. Länken för jämförelse öppnar introduktionen igen.
- Kontrollera personligt färgtema, kö, ärendedetalj, kommentar och bilagor i båda layouterna. Samma aktuella data och behörigheter används; detta återställer inte gamla controllers eller databaser.
- Kunden behåller den nya kundportalen med egna färgteman och får inget klassiskt layoutval.

För att åter stänga av valet avmarkerar superadmin reglaget och sparar. Då visas den moderna layouten; sparade personliga layoutval raderas inte. Detta är ingen återställning av programversion eller databas.

## Miljö, webbserver och data

- Befintliga mejlkonton, inkorgar, företagens mejlprofiler och sparade inställningar ska ligga kvar. Upgrade-paketet innehåller programkod och migrationer, inte en ersättningsdatabas. Privata miljöfiler och `var/` undantas från kodkopieringen. Bevara `APP_SECRET` samt separata `DRIFTPUNKT_MAIL_SECRET_KEY`-/MFA-nycklar: annars kan sparade krypterade hemligheter bli oläsbara även om kontona finns kvar. Kör inte `manage.sh init`, `app:install:fresh` eller fixtures på en befintlig installation, och ersätt inte miljön med paketets exempelkonfiguration.
- Bevara installationens profilfärger, logotyper och personliga temaval. Publika sidor följer enhetens ljusa/mörka tema som standard; admin och superadmin kan stänga av detta i inställningarna. Högtidsdekoration är diskret och kan stängas av separat. Befintliga sparade val ska inte nollställas vid uppdateringen.
- För supportkort krävs en korrekt kanonisk publik basadress i `DRIFTPUNKT_PUBLIC_BASE_URL`, normalt HTTPS. Tomt värde tillåter start men inte giltig kortlänk/export. Bevara `APP_SECRET`. Kortlogotyper under programmets privata `var/` måste ingå i volymbackup; nedladdade exporter behöver hanteras enligt verksamhetens egen backup-/sekretessrutin.
- Uppdatera loggmaskering i **alla** aktiva HTTP-/HTTPS-vhostar och eventuella proxyloggar för `/supportkort/<token>`. Befintliga Certbot-SSL-vhostar får inte automatiskt den nya Apache-konfigurationen. Syntaxkontrollera och ladda om webbservern enligt plattformens rutin. Applikationsloggens maskering ersätter inte proxy-/accessloggskyddet.
- Uppdatera app och schemaläggare till samma kod, beroenden och nödvändiga extensions. Paketets `composer.lock` ska användas; kör inte `composer update` på liveservern.

## Genomförande och efterkontroll

1. Aktivera underhåll och använd **Superadmin → Uppdateringar** för den installationstyp som stöder webbuppdatering. Ladda upp och förkontrollera paketet, säkerhetskopiera och köa först när förutsättningarna ovan är uppfyllda. För avbildningsbaserad drift följs den fastställda avbildningsrutinen för båda tjänsterna.
2. Följ körningen och alla eftersteg. Alla väntande migrationer ska köras, inklusive versionsaviseringens `Version20261114120000` och supportkortens `Version20261114190000` när de inte redan finns. Kontrollera migrationernas faktiska status; en uppdatering från äldre 1.x kan kräva fler. Cacheuppvärmning har separat 512 MB-budget.
3. Bekräfta inte en fungerande release förrän kod, Composer, migrationer och cache är gröna. Kontrollera versionsmetadata i både app och schemaläggare samt loggar utan att exponera hemligheter. Återuppta inte mejlintag före färdigt schema.
4. Verifiera inloggning/MFA, privatkund utan företag som skapar ärende, företagskund med rätt företagsgränser, tekniker-/koordinatorflöden och tydliga företagsmärken. Kontrollera personliga färgteman, publika profilfärger i båda systemtemana och textredigeraren: grundverktyg synliga, avancerade hopfällda, dölj/visa bevarar innehållet.
5. Kontrollera supportkortens rättigheter, QR/länk och export om funktionen används. Inaktivering av företag spärrar gamla kortlänkar permanent; återaktivering kräver ny uttrycklig rotation och nya utskrifter. Kontrollera versionsaviseringen mot den officiella releasen utan automatisk installation.
6. Stäng underhåll efter godkända tekniska kontroller, återuppta verksamhetsjobb och genomför kontrollerade roll-/utskicksprov. Verifiera verklig SMTP, mejlklienter, proxy/TLS och PWA på berörda enheter. Spara inte oskickad ärendetext enbart i en flik inför omladdning.
7. Dokumentera resultat och avvikelser. Vid fel: håll underhåll aktivt och återställ samordnat kod **och** databas enligt provad rutin. Gammal kod är inte automatiskt kompatibel med ett ändrat schema.

### Om en äldre körning inte registrerar friskbekräftelsen

2.0.0 registrerar slutstatus i en ny process efter cachebytet. En uppdateringsprocess som redan startats med äldre kod får däremot inte antas byta runner mitt i körningen. Om kodkopieringen är klar men ett fel hänvisar till en saknad gammal `var/cache/prod/Container…`-fil:

1. Behåll underhåll och pausa mejlintag. Köa inte samma uppdatering upprepade gånger.
2. Kontrollera att rätt paket verkligen applicerats och att Composer, alla migrationer och cachebyggnad är färdiga. Granska ursprungskörningen och utför endast saknade eftersteg med den installerade kodens CLI eller superadminfunktioner.
3. När dessa kontroller är gröna kan en administratör i en **ny CLI-process**, med den installerade webbserveranvändaren och rätt miljö, registrera paketets verkliga namn/version: `php bin/console app:code-update:mark-pending 'Driftpunkt' '2.0.0' --env=prod --no-debug`. Detta är en statusregistrering, ingen installation och inget friskintyg.
4. Gör efterkontrollerna och bekräfta först därefter releasen som fungerande i superadmin. Spara den ursprungliga felloggen.

## Verifieringsgräns

Tidigare NAS-prov gäller respektive dokumenterad kandidat. De innebär inte att liveserverns backupåterställning, TLS-konfiguration, externa integrationer, faktiska mejlklienter eller båda personallayouterna är godkända i den verkliga miljön. Kontrollera dessa på den avsedda driftsättningen innan produktionsacceptans.
