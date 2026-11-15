# Upgrade to Driftpunkt 2.0.0 – prepare the live server

These are operational instructions, not confirmation that your live environment has passed the checks. Rehearse on an isolated copy with outgoing messages and external integrations blocked.

## Before upgrading

1. Inventory the installed version, web/worker PHP, database, enabled addons, web server/proxy, Compose files and persistent volumes. Protect configuration snapshots and never expose secrets in logs/screenshots.
2. Back up database, code, private environment, encryption keys, attachments, uploaded logos and persistent volumes together. Preserve `APP_SECRET` and separate MFA/mail keys. Test restoration in isolation. A database dump alone is insufficient; support-card links also depend on their key and database.
3. Check space for archives, extraction, code/database backups and Composer/cache. Superadmin preflight must pass, including write access to files such as `.dockerignore`. Identify the actual service user; never apply blanket `chmod 777` or recursive ownership changes to database volumes.
4. Verify supported 64-bit PHP and extensions, especially **GD with JPEG/PNG in both application and scheduler**. Missing extensions require runtime/image changes; ZIP archives cannot install them. Run `composer check-platform-reqs --no-dev` with the installation's PHP/Composer.
5. Download the official **upgrade archive**, not the fresh-install archive. Compare SHA-256 with a trusted release source. Never run fresh installation, fixtures or the test suite against a production database.
6. Plan a maintenance window and pause mail intake/business jobs during code/schema changes. Keep the process consuming the code-update queue available. Verify background launching before queuing: an ordinary mail/SLA scheduler is not automatically a code-update worker.

### Docker: identify how application code is deployed

Inspect actual Compose files and mounts before recreating anything. Substitute the correct container name:

```bash
docker inspect <app-container> --format '{{index .Config.Labels "com.docker.compose.project.config_files"}}'
docker inspect <app-container> --format '{{range .Mounts}}{{println .Source "->" .Destination}}{{end}}'
```

When the whole code directory is shared between app/scheduler, web code updates may update both. If code is **inside the image**, both services need an image built from the same new version. Updating only the app container neither updates its image nor the scheduler, and changes may disappear on recreation. Do not rebuild from stale source after a web update. The supplied `deploy/nas/compose.yaml` is image-based.

## Enable classic/modern staff layout choice

The choice is **off by default**. Super administrators can enable it in the UI:

1. Open **Settings** and search for **Portal layout**. In Swedish, search **Portalens layout**, “klassisk”, “tekniker” or “layout”.
2. Enable the option allowing technicians and ticket coordinators to choose classic or modern presentation.
3. Save the layout setting. No restart is required.

Only super administrators can change this switch, including through direct requests. Disabling it selects modern presentation without deleting personal layout/colour preferences. Re-enabling reuses valid acknowledged choices; otherwise the introduction is shown. Verify both presentations in the intended environment before broad activation.

`DRIFTPUNKT_INTERNAL_LAYOUT_CHOICE_ENABLED` remains the default only until superadmin saves a decision. A stored UI choice takes precedence for both enabled/disabled states. Changing the environment does not overwrite that decision. Docker container-environment changes need recreation; saving the UI switch does not.

### Verify the choice

- Use actual technician/coordinator accounts. Admin role inheritance alone does not grant this personal choice.
- On first visit, choose and save Modern or Classic. Without a valid acknowledgement, the enabled feature uses classic presentation until the introduction is completed.
- In personal settings, change the layout, save and reload; check persistence after sign-in. The comparison link reopens the introduction.
- Check colours, queue, ticket detail, comments and attachments in both layouts. They use current data/permissions, not old controllers or databases.
- Customers retain the modern customer portal with personal themes and have no classic-layout option.

Turning the feature off is not a code/database rollback and does not erase saved personal choices.

## Environment, web server and data

- Existing mail servers, inboxes, company mail profiles and saved settings must remain. The upgrade contains code/migrations, not a replacement database. Private environment files and `var/` are excluded from code copying. Keep `APP_SECRET` and separate `DRIFTPUNKT_MAIL_SECRET_KEY`/MFA keys: otherwise encrypted credentials may become unreadable even when account records remain. Never run `manage.sh init`, `app:install:fresh` or fixtures on an existing installation, or replace its environment with examples.
- Preserve installation colours, logos and personal themes. Public pages follow the device theme by default; admin/superadmin can disable this and seasonal decoration separately. Existing preferences must not be reset.
- Support cards need canonical `DRIFTPUNKT_PUBLIC_BASE_URL`, normally HTTPS. An empty value permits startup but not a valid link/export. Back up private card logos in `var/` together with keys/database; protect downloaded exports too.
- Mask `/supportkort/<token>` in **all** active HTTP/HTTPS virtual hosts and proxies. Existing Certbot SSL hosts do not automatically receive changed Apache examples. Syntax-check/reload correctly. App-log redaction does not replace access-log controls.
- Keep app/scheduler code, locked dependencies and extensions aligned. Use the included `composer.lock`; never run `composer update` on the live server.

## Apply and verify

1. Enable maintenance. For a compatible deployment, use **Superadmin → Updates** to upload, preflight, back up and queue the package. Image-based deployments must instead replace both service images according to their operational procedure.
2. Monitor every step. Apply all pending migrations, including `Version20261114120000` and `Version20261114190000` where missing; older 1.x versions may require more. Verify actual migration status. Cache warmup has a separate 512 MB budget.
3. Do not confirm release health until code, Composer, migrations and cache pass. Check version metadata in app/scheduler and inspect logs without exposing secrets. Resume mail intake only after schema completion.
4. Verify login/MFA, private-customer submission without a company, correct company boundaries, staff workflows and company labels. Check personal/public themes and editor behaviour: basic tools visible, advanced tools collapsed, hide/show preserving content.
5. If used, verify support-card grants, QR/link and export. Company deactivation permanently revokes card links; reactivation needs explicit rotation and reprinting. Verify stable-release notices without automatic installation.
6. Disable maintenance/resume jobs after technical checks. Verify controlled mail delivery, clients, proxy/TLS and affected PWA devices in the intended deployment. Do not rely on an unsaved browser draft surviving reload.
7. Record results/deviations. On failure, keep maintenance and restore compatible code **and** data using a rehearsed procedure. Older code is not automatically compatible with a changed schema.

### An older run did not register pending confirmation

2.0.0 registers completion in a fresh process after cache replacement. A process already started with old code does not switch runners mid-execution. If files were copied but a failure mentions a missing old `var/cache/prod/Container…` file:

1. Keep maintenance, pause mail and do not repeatedly queue the same package.
2. Verify the correct package was applied and Composer, migrations and cache finished. Review the original run; perform only missing post-update tasks through installed-code CLI or superadmin tools.
3. Only after those checks pass, an administrator may use a **new CLI process** with the proper service user/environment to register the actual package/version: `php bin/console app:code-update:mark-pending 'Driftpunkt' '2.0.0' --env=prod --no-debug`. This records status; it does not install or certify release health.
4. Complete after-checks, then confirm health in superadmin. Retain the original failure log.

## Verification boundary

Earlier NAS checks apply only to their documented candidates. They do not prove live restore, TLS, external integrations, real mail clients or every staff-layout/device combination. The release owner accepted stable 2.0.0 with real email, production HTTPS and full restoration explicitly unverified. Validate the intended deployment before relying on those functions in production.
