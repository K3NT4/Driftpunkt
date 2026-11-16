# Upgrade to Driftpunkt 2.0.1

Use the **upgrade archive** for an existing installation. The installation archive and `app:install:fresh` are for a new, empty database. For an installation older than 2.0, also read the [2.0 upgrade checklist](upgrade_to_2_0_0.md).

## Before upgrading

1. Verify PHP 8.4.27+ within 8.4, or PHP 8.5.12+ within 8.5, and the required extensions. Keep the existing supported PHP series.
2. Save a consistent database backup and a file backup outside the application directory. Include uploads, branding, private environment files, all encryption keys and `var/declarative_addons/` when present. Check the archives and rehearse restoration in an isolated copy.
3. Inventory old addons. Executable PHP/Twig ZIP addons no longer load and cannot be imported or activated. Their catalog and package history are retained for inventory.
4. Keep `APP_SECRET`, separate encryption keys and mail configuration unchanged. Check application-user write access to managed code paths and `var/`.
5. Download the intended upgrade package and its `.sha256` file from the same release. Verify them before uploading:

```bash
sha256sum --check driftpunkt-upgrade-2.0.1.zip.sha256
```

Expect `OK`. Do not use an archive with a failed checksum or the withdrawn early test candidate.

## Upgrade through the web interface

1. Sign in as a super administrator and open **Updates**.
2. Upload `driftpunkt-upgrade-2.0.1.zip` and check its validation result.
3. Enable maintenance mode. Apply the validated package once and let the background run finish.
4. Check that the run reports `completed` and the installed version is `2.0.1`.
5. Check administration, an existing ticket, news and the new **Features** and **Addons** pages.
6. Confirm the update as healthy. This returns the site to normal operation. Then check customer, technician and coordinator access.

The updater makes a code backup and runs Composer, migrations and production cache steps. Migration `Version20261116120000` preserves the old News Editor Plus choice as a native preference, keeps existing native preferences and defaults telephone support to enabled. It does not delete historical addon records or content.

## Changes to check

- **Features**: extended news editing and telephone support are built-in switches. Turning them off retains their content and history. Telephone support's direct URL returns 404 when disabled.
- **Addons**: JSON example, validation, preview, inactive import and explicit version activation. See the [addon guide](addons.md).
- **SupportDesk**: its settings and remote-support shortcuts are removed. The old settings URL redirects to Features.
- Existing company support cards, revocable QR links and PDF/SVG exports remain available.
- Public panel content is visible without sign-in. Customer panels are shared across customer accounts, not isolated by company.

## If an error appears

Do not upload or apply the package a second time. First read the update run's final status and error details. A useful independent startup check is:

```bash
php bin/console about --env=prod --no-debug
```

An unchanged 2.0.0 update process can report a missing console-listener cache file when terminating after a successfully recorded `completed` update. Check the final status and start a fresh request/process before concluding that the update failed. Version 2.0.1 resolves the relevant error and termination listeners before a future update replaces its running process's cache. This does not retroactively change an already running older process.

A `failed` run, persistent HTTP 500 or failed fresh-process check requires investigation. Use the retained code and database backups as a matching restoration set when rollback is necessary; do not blindly delete application directories, reset preferences or rerun irreversible migrations. Restore file ownership and rebuild the cache for the restored code before reopening the site.

## Verification scope

The ordinary PHP 8.4.27 suite completed with 1,289 tests and 29,047 assertions, zero failures/errors and one skipped PDF text-extraction check requiring an unavailable external tool. Visual snapshots are outside that suite. A NAS upgrade was confirmed, and a separate Debian/MariaDB deployment exercised the web update, six role accounts, addon lifecycle, all three preview languages, native switches and isolated database/file restoration. Real email delivery, production HTTPS and load behaviour still require environment-specific verification.
