# Säkerhetskrav och nuvarande skydd

Detta dokument beskriver vad som faktiskt finns i versionen och vad som bör gälla i drift.

## Det som redan finns

- hashade lösenord via Symfonys security-stack
- krav på minst 12 tecken vid självregistrering och lösenordsåterställning
- CSRF-skydd i formulärflöden
- rollbaserad åtkomstkontroll för portal och admin
- underhållskontroll som kan stoppa inloggningar
- e-postflöde med draftgranskning för osäkra fall
- notifieringslogg för spårbarhet

## Säkerhetskrav i drift

- kör produktion över HTTPS
- lägg inte riktiga hemligheter i `.env`
- sätt korrekta filrättigheter på `var/`
- begränsa tillgång till backupfiler och staged uppdateringsfiler
- övervak loggar för misslyckad inloggning, pollingfel och jobbmisslyckanden

## Konto- och behörighetskrav

- skapa första admin via CLI
- använd minsta nödvändiga behörighet för vardagligt arbete
- håll inaktiva konton avstängda
- använd e-postnotifieringar och MFA-flagga enligt intern policy

## Mailrelaterade risker

Största risken i nuläget är felaktig automatisk behandling av inkommande e-post. Därför är draftgranskning och mailoverrides viktiga skyddsmekanismer.

## Backup och uppdatering

Backup och uppdateringspaket innehåller känslig data eller kod och ska behandlas som administrativa artefakter, inte som publika filer.

## Kvarvarande säkerhetsarbete

Se `known_limitations.md` för sådant som inte bör översäljas som helt färdigt, till exempel bredare hardening, extern revision eller avancerad audit och SIEM-integrering.
