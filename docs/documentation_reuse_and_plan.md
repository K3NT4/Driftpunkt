# Dokumentationskarta och forvaltningsplan

Detta dokument ersatter den tidigare planen for ateranvandning. Fokus nu ar att halla dokumentationen synkad med koden.

## Dokument som beskriver nulaget

- `driftpunkt_ticket_system_spec.md`: vad produkten ar i den har versionen
- `product_scope_and_mvp.md`: vad som ingar och inte ingar
- `installation_and_deployment.md`: installation, lokal korning och driftsattning
- `roles_and_permissions.md`: roller och behorigheter
- `data_model.md`: central datamodell
- `ticket_lifecycle_and_visibility.md`: ticketfloden
- `customer_portal_experience.md`: publik och kundnara upplevelse
- `admin_information_architecture.md`: adminstruktur
- `mail_configuration_guide.md`: mailobjekt och uppsattning
- `mail_processing_rules.md`: behandling av inkommande mail
- `mail_polling_operations.md`: polling i drift
- `ticket_attachment_archiving_operations.md`: bilagearkivering
- `operational_model.md`: den dagliga driftsmodellen
- `security_requirements.md`: skydd och krav
- `testing_and_quality.md`: testlage och kvalitetssignal
- `known_limitations.md`: kanda begransningar

## Nar dokumentationen ska uppdateras

Uppdatera dokumentationen vid:

- nya routes eller nya portalfloden
- nya CLI-kommandon
- andrade roller eller visibilityregler
- andrad installation eller releaseprocess
- nya driftjobb eller bakgrundsjobb

## Praktisk regel

Om en funktion inte gar att peka pa i kod, route, kommando eller test ska den inte beskrivas som fardig.

## Rekommenderad arbetsordning framover

1. andra kod eller tests
2. verifiera route/kommando/test
3. uppdatera berort dokument
4. uppdatera README om anvandar- eller driftfloden har andrats
