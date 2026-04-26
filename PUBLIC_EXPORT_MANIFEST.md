# Public Export Manifest

Skapad: 2026-04-26T11:20:38+00:00

## Källrepo

- Privat huvudrepo: `Driftpunkt-privat`
- Målrepo: `-Driftpunkt`

## Exportprincip

- Baseras på en whitelist av publika projektmappar.
- Privata moduler, driftkod, mailflöden och interna releaseverktyg filtreras bort.
- Kontrollera diffen i den publika repot innan push.

## Exkluderade huvudområden

- `config/packages/mailer.yaml`
- `config/routes/security.yaml`
- `deploy`
- `src/Module/Mail`
- `src/Module/Portal`
- `src/Module/System/Service/BackgroundConsoleLauncher.php`
- `src/Module/System/Service/CodeUpdateManager.php`
- `src/Module/System/Service/DatabaseMaintenanceJobRunner.php`
- `src/Module/System/Service/DatabaseMaintenanceService.php`
- `src/Module/System/Service/PostUpdateTaskRunner.php`
- `src/Module/System/Service/ProjectCommandExecutor.php`
- `src/Module/System/Service/PublicRepoExporter.php`
- `src/Module/System/Service/ReleasePackageBuilder.php`
- `src/Command/BuildReleasePackagesCommand.php`
- `src/Command/ExportPublicRepoCommand.php`
- `src/Command/IngestIncomingMailCommand.php`
- `src/Command/PollSupportMailboxesCommand.php`
- `src/Command/RunDatabaseMaintenanceJobCommand.php`
- `src/Command/RunPostUpdateTasksCommand.php`
- `templates/emails`
- `templates/portal`

## Publik dokumentation

Endast följande `docs/`-sökvägar ingår i exporten:

- `docs/debian_server_setup.md`
- `docs/driftpunkt_ticket_system_spec.md`
- `docs/installation_and_deployment.md`
- `docs/known_limitations.md`
- `docs/mail_configuration_guide.md`
- `docs/mail_polling_operations.md`
- `docs/mail_processing_rules.md`
- `docs/nas_docker_setup.md`
- `docs/security_requirements.md`
- `docs/ticket_attachment_archiving_operations.md`
- `docs/public-assets`

## Kopierade filer

- `README.md`
- `bin/console`
- `bin/phpunit`
- `composer.json`
- `composer.lock`
- `config/bootstrap_env.php`
- `config/bundles.php`
- `config/packages/cache.yaml`
- `config/packages/doctrine.yaml`
- `config/packages/doctrine_migrations.yaml`
- `config/packages/framework.yaml`
- `config/packages/monolog.yaml`
- `config/packages/property_info.yaml`
- `config/packages/routing.yaml`
- `config/packages/security.yaml`
- `config/packages/translation.yaml`
- `config/packages/twig.yaml`
- `config/packages/validator.yaml`
- `config/preload.php`
- `config/reference.php`
- `config/routes/framework.yaml`
- `config/routes.yaml`
- `config/services.yaml`
- `docs/debian_server_setup.md`
- `docs/driftpunkt_ticket_system_spec.md`
- `docs/installation_and_deployment.md`
- `docs/known_limitations.md`
- `docs/mail_configuration_guide.md`
- `docs/mail_polling_operations.md`
- `docs/mail_processing_rules.md`
- `docs/nas_docker_setup.md`
- `docs/public-assets/branding/logo-icon.png`
- `docs/public-assets/branding/logo-wide.png`
- `docs/public-assets/screenshots/admin-dashboard.png`
- `docs/public-assets/screenshots/customer-portal.png`
- `docs/public-assets/screenshots/homepage.png`
- `docs/public-assets/screenshots/login-admin.png`
- `docs/public-assets/screenshots/login-customer.png`
- `docs/public-assets/screenshots/login-technician.png`
- `docs/public-assets/screenshots/technician-portal.png`
- `docs/security_requirements.md`
- `docs/ticket_attachment_archiving_operations.md`
- `migrations/.gitignore`
- `migrations/Version20260418072614.php`
- `migrations/Version20260418074303.php`
- `migrations/Version20260418075147.php`
- `migrations/Version20260418075719.php`
- `migrations/Version20260418080308.php`
- `migrations/Version20260418081100.php`
- `migrations/Version20260418082500.php`
- `migrations/Version20260418084000.php`
- `migrations/Version20260418085500.php`
- `migrations/Version20260418090500.php`
- `migrations/Version20260418092000.php`
- `migrations/Version20260418092100.php`
- `migrations/Version20260418092200.php`
- `migrations/Version20260418093000.php`
- `migrations/Version20260418093100.php`
- `migrations/Version20260418093200.php`
- `migrations/Version20260418094000.php`
- `migrations/Version20260418094100.php`
- `migrations/Version20260418094200.php`
- `migrations/Version20260418095000.php`
- `migrations/Version20260418095100.php`
- `migrations/Version20260418095200.php`
- `migrations/Version20260418096000.php`
- `migrations/Version20260418097000.php`
- `migrations/Version20260418097100.php`
- `migrations/Version20260418098000.php`
- `migrations/Version20260418098100.php`
- `migrations/Version20260418099000.php`
- `migrations/Version20260418100000.php`
- `migrations/Version20260418101000.php`
- `migrations/Version20260418101100.php`
- `migrations/Version20260418102000.php`
- `migrations/Version20260418103000.php`
- `migrations/Version20260418103100.php`
- `migrations/Version20260418113524.php`
- `migrations/Version20260418114214.php`
- `migrations/Version20260418114721.php`
- `migrations/Version20260418115120.php`
- `migrations/Version20260418115525.php`
- `migrations/Version20260418115902.php`
- `migrations/Version20260418121222.php`
- `migrations/Version20260418123000.php`
- `migrations/Version20260418124500.php`
- `migrations/Version20260418153416.php`
- `migrations/Version20260418173000.php`
- `migrations/Version20260418184500.php`
- `migrations/Version20260418191500.php`
- `migrations/Version20260418194500.php`
- `migrations/Version20260418200000.php`
- `migrations/Version20260418203000.php`
- `migrations/Version20260418210000.php`
- `migrations/Version20260418213000.php`
- `migrations/Version20260418220000.php`
- `migrations/Version20260418223000.php`
- `migrations/Version20260419100000.php`
- `migrations/Version20260419113000.php`
- `migrations/Version20260419120000.php`
- `migrations/Version20260419130000.php`
- `migrations/Version20260420190000.php`
- `migrations/Version20260421090000.php`
- `migrations/Version20260421100000.php`
- `migrations/Version20260421110000.php`
- `migrations/Version20260421120000.php`
- `migrations/Version20260421130000.php`
- `migrations/Version20260421140000.php`
- `migrations/Version20260421160000.php`
- `migrations/Version20260421170000.php`
- `migrations/Version20260421180000.php`
- `migrations/Version20260422090000.php`
- `migrations/Version20260423170000.php`
- `migrations/Version20260423183000.php`
- `migrations/Version20260423190000.php`
- `migrations/Version20260423193000.php`
- `migrations/Version20260423210000.php`
- `migrations/Version20260423213000.php`
- `migrations/Version20260424120000.php`
- `migrations/Version20260424133000.php`
- ... och ytterligare 181 filer

## Överhoppade sökvägar i denna körning

- `config/packages/mailer.yaml`
- `config/routes/security.yaml`
- `docs/Kundportal.png`
- `docs/addon_build_and_release_guide.md`
- `docs/admin sida.png`
- `docs/admin_information_architecture.md`
- `docs/customer_portal_experience.md`
- `docs/data_model.md`
- `docs/documentation_reuse_and_plan.md`
- `docs/driftpunktlogo ikon.png`
- `docs/driftpunktlogo paket.png`
- `docs/driftpunktlogo paket.psd`
- `docs/driftpunktlogo stor.png`
- `docs/logga in kund.png`
- `docs/operational_model.md`
- `docs/product_scope_and_mvp.md`
- `docs/roles_and_permissions.md`
- `docs/startsida.png`
- `docs/superpowers`
- `docs/teknikerportal.png`
- `docs/testing_and_quality.md`
- `docs/ticket_lifecycle_and_visibility.md`
- `src/Command/BuildReleasePackagesCommand.php`
- `src/Command/ExportPublicRepoCommand.php`
- `src/Command/IngestIncomingMailCommand.php`
- `src/Command/PollSupportMailboxesCommand.php`
- `src/Command/RunDatabaseMaintenanceJobCommand.php`
- `src/Command/RunPostUpdateTasksCommand.php`
- `src/Module/Mail`
- `src/Module/Portal`
- `src/Module/System/Service/BackgroundConsoleLauncher.php`
- `src/Module/System/Service/CodeUpdateManager.php`
- `src/Module/System/Service/DatabaseMaintenanceJobRunner.php`
- `src/Module/System/Service/DatabaseMaintenanceService.php`
- `src/Module/System/Service/PostUpdateTaskRunner.php`
- `src/Module/System/Service/ProjectCommandExecutor.php`
- `src/Module/System/Service/PublicRepoExporter.php`
- `src/Module/System/Service/ReleasePackageBuilder.php`
- `templates/emails`
- `templates/portal`
