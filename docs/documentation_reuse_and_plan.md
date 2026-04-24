# Dokumentationskarta och förvaltningsplan

Detta dokument ersätter den tidigare planen för återanvändning. Fokus nu är att hålla dokumentationen synkad med koden.

## Dokument som beskriver nuläget

- `driftpunkt_ticket_system_spec.md`: vad produkten är i den här versionen
- `product_scope_and_mvp.md`: vad som ingår och inte ingår
- `installation_and_deployment.md`: installation, lokal körning och driftsättning
- `roles_and_permissions.md`: roller och behörigheter
- `data_model.md`: central datamodell
- `ticket_lifecycle_and_visibility.md`: ticketflöden
- `customer_portal_experience.md`: publik och kundnära upplevelse
- `admin_information_architecture.md`: adminstruktur
- `mail_configuration_guide.md`: mailobjekt och uppsättning
- `mail_processing_rules.md`: behandling av inkommande mail
- `mail_polling_operations.md`: polling i drift
- `ticket_attachment_archiving_operations.md`: bilagearkivering
- `operational_model.md`: den dagliga driftsmodellen
- `security_requirements.md`: skydd och krav
- `testing_and_quality.md`: testlage och kvalitetssignal
- `known_limitations.md`: kända begränsningar

## När dokumentationen ska uppdateras

Uppdatera dokumentationen vid:

- nya routes eller nya portalflöden
- nya CLI-kommandon
- ändrade roller eller visibilityregler
- ändrad installation eller releaseprocess
- nya driftjobb eller bakgrundsjobb

## Praktisk regel

Om en funktion inte går att peka på i kod, route, kommando eller test ska den inte beskrivas som färdig.

## Rekommenderad arbetsordning framöver

1. ändra kod eller tests
2. verifiera route/kommando/test
3. uppdatera berört dokument
4. uppdatera README om användar- eller driftflöden har ändrats
