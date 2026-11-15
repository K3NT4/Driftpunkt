# Public Export Manifest

Created: 2026-11-15T07:26:54+00:00

## Export Policy

- Public exports contain release packages only.
- The latest installation package is included.
- The latest upgrade package is cumulative and should normally be used first.
- Older upgrade packages are retained as fallback and release history.
- Up to the latest 3 upgrade packages are included.
- Selected logo and screenshot assets are included for README presentation.
- License, security policy, issue templates, and the manual release workflow are copied from the development repository.
- Application code and dependencies are included inside the release archives. Private Git history, live environment files, deployment secrets, runtime customer data, and internal planning documents are excluded.

## Exported Packages

| Type | Version | Package | SHA-256 |
| --- | --- | --- | --- |
| install | 2.0.0 | `packages/driftpunkt-install-2.0.0.zip` | `997d38437c6997109c667e6956d6b342f08de2e21457edc69d1e878d4bb81013` |
| upgrade | 2.0.0 | `packages/driftpunkt-upgrade-2.0.0.zip` | `390515448b554f8a1839101a0bbbf10bdd612ea935f023ff7e102dcef694a34c` |
| upgrade | 1.0.133 | `packages/driftpunkt-upgrade-1.0.133.zip` | `84ba3e9d00f8138018ef0c79ace40942d5a37d1a36bd6dd60d8effccdd4832cb` |
| upgrade | 1.0.132 | `packages/driftpunkt-upgrade-1.0.132.zip` | `f5cc749393dbfdf398bbd7725d594689d8fc1fc2c6682752fbd1ade528fcc6b3` |

## Exported README Assets

- `assets/branding/logo-wide.png`
- `assets/branding/logo-icon.png`
- `assets/screenshots/homepage.png`
- `assets/screenshots/public-status.png`
- `assets/screenshots/contact-page.png`
- `assets/screenshots/maintenance-mode.png`
- `assets/screenshots/customer-portal.png`
- `assets/screenshots/customer-ticket.png`
- `assets/screenshots/customer-mobile.png`
- `assets/screenshots/technician-portal.png`
- `assets/screenshots/technician-overview.png`
- `assets/screenshots/technician-tickets.png`
- `assets/screenshots/coordinator-portal.png`
- `assets/screenshots/admin-dashboard.png`
- `assets/screenshots/login-admin.png`
- `assets/screenshots/login-technician.png`
- `assets/screenshots/login-customer.png`
