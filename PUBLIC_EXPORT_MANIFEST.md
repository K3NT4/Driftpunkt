# Public Export Manifest

Created: 2026-11-16T21:19:56+00:00

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
| install | 2.0.1 | `packages/driftpunkt-install-2.0.1.zip` | `94497c2170a8cb80d6743743194eecd9b7781f6928dd48485b5f3915c18538b4` |
| upgrade | 2.0.1 | `packages/driftpunkt-upgrade-2.0.1.zip` | `eee116af933b1baa838a9f658e3673cf7d4038313ee1cca14530adfc3ba633a9` |
| upgrade | 2.0.0 | `packages/driftpunkt-upgrade-2.0.0.zip` | `68d4b116fe045296586a868e002096f580e9df37d9299c844dc532bff8c1084a` |
| upgrade | 1.0.133 | `packages/driftpunkt-upgrade-1.0.133.zip` | `84ba3e9d00f8138018ef0c79ace40942d5a37d1a36bd6dd60d8effccdd4832cb` |

## Exported README Assets

- `assets/branding/logo-wide.png`
- `assets/branding/logo-icon.png`
- `assets/branding/logo-wide-v2.svg`
- `assets/branding/logo-wide-v2-dark.svg`
- `assets/branding/driftpunkt-icon-v2.svg`
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

## Release Packages Not Exported

- `dist/driftpunkt-upgrade-1.0.132.zip`
