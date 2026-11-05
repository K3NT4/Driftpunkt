# Public Export Manifest

Created: 2026-11-05T20:37:30+01:00

## Export Policy

- Public exports contain release packages only.
- The latest installation package is included.
- The latest upgrade package is cumulative and should normally be used first.
- Older upgrade packages are retained as fallback and release history.
- Up to the latest 3 upgrade packages are included.
- Selected logo and screenshot assets are included for README presentation.
- Application code and dependencies are included inside the release archives. Private Git history, live environment files, deployment secrets, runtime customer data, and internal planning documents are excluded.

## Exported Packages

| Type | Version | Package | SHA-256 |
| --- | --- | --- | --- |
| install | 1.0.133 | `packages/driftpunkt-install-1.0.133.zip` | `bb3e8910c81ce720bbb772b0abd316485125bad38457f50ba895894cede5aefd` |
| upgrade | 1.0.133 | `packages/driftpunkt-upgrade-1.0.133.zip` | `84ba3e9d00f8138018ef0c79ace40942d5a37d1a36bd6dd60d8effccdd4832cb` |
| upgrade | 1.0.132 | `packages/driftpunkt-upgrade-1.0.132.zip` | `f5cc749393dbfdf398bbd7725d594689d8fc1fc2c6682752fbd1ade528fcc6b3` |
| upgrade | 1.0.131 | `packages/driftpunkt-upgrade-1.0.131.zip` | `4ba0120dcfd88781eaa3b52809b1b9939fbf55753fc7b0895f9fd175c8e8bb46` |

## Exported README Assets

- `assets/branding/logo-wide.png`
- `assets/branding/logo-icon.png`
- `assets/screenshots/homepage.png`
- `assets/screenshots/public-status.png`
- `assets/screenshots/contact-page.png`
- `assets/screenshots/maintenance-mode.png`
- `assets/screenshots/customer-portal.png`
- `assets/screenshots/technician-portal.png`
- `assets/screenshots/technician-overview.png`
- `assets/screenshots/technician-tickets.png`
- `assets/screenshots/coordinator-portal.png`
- `assets/screenshots/admin-dashboard.png`
- `assets/screenshots/login-admin.png`
- `assets/screenshots/login-technician.png`
- `assets/screenshots/login-customer.png`
