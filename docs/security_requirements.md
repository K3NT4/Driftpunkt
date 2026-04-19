# Sakerhetskrav och nuvarande skydd

Detta dokument beskriver vad som faktiskt finns i versionen och vad som bor galla i drift.

## Det som redan finns

- hashade losenord via Symfonys security-stack
- krav pa minst 12 tecken vid sjalvregistrering och losenordsaterstallning
- CSRF-skydd i formularfloden
- rollbaserad atkomstkontroll for portal och admin
- underhallskontroll som kan stoppa inloggningar
- e-postflode med draftgranskning for osakra fall
- notifieringslogg for spårbarhet

## Sakerhetskrav i drift

- kor produktion over HTTPS
- lagg inte riktiga hemligheter i `.env`
- satt korrekta filrattigheter pa `var/`
- begransa tillgang till backupfiler och staged uppdateringsfiler
- overvak loggar for misslyckad inloggning, pollingfel och jobbmisslyckanden

## Konto- och behorighetskrav

- skapa forsta admin via CLI
- anvand minsta nodvandiga behorighet for vardagligt arbete
- hall inaktiva konton avstangda
- anvand e-postnotifieringar och MFA-flagga enligt intern policy

## Mailrelaterade risker

Storsta risken i nulaget ar felaktig automatisk behandling av inkommande e-post. Därfor ar draftgranskning och mailoverrides viktiga skyddsmekanismer.

## Backup och uppdatering

Backup och uppdateringspaket innehaller kanslig data eller kod och ska behandlas som administrativa artefakter, inte som publika filer.

## Kvarvarande sakerhetsarbete

Se `known_limitations.md` for sadant som inte bor oversaljas som helt fardigt, till exempel bredare hardening, extern revision eller avancerad audit och SIEM-integrering.
