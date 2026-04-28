# Public Export Manifest

Created: 2026-04-28T11:10:10+00:00

## Export Policy

- Public exports contain release packages only.
- The latest installation package is included.
- Up to the latest 3 upgrade packages are included.
- Selected logo and screenshot assets are included for README presentation.
- Application source code, private modules, internal release tooling, environment files, and deployment secrets are intentionally excluded.

## Exported Packages

| Type | Version | Package | SHA-256 |
| --- | --- | --- | --- |
| install | 1.0.11 | `packages/driftpunkt-install-1.0.11.zip` | `cdb90ef584013ad9e65258575ebd9063cccd7a39ea0f939cc6edb1dafee78e6c` |
| upgrade | 1.0.11 | `packages/driftpunkt-upgrade-1.0.11.zip` | `5743eb2df2a96991a73817460e1993427956d4dea1386870613ba1ec0bf76b06` |
| upgrade | 1.0.10 | `packages/driftpunkt-upgrade-1.0.10.zip` | `56018f94aaaed93dc291c6c734ad6a855e2e74d62f729c8ccc096b7cf9b2956f` |

## Exported README Assets

- `assets/branding/logo-wide.png`
- `assets/branding/logo-icon.png`
- `assets/screenshots/homepage.png`
- `assets/screenshots/customer-portal.png`
- `assets/screenshots/technician-portal.png`
- `assets/screenshots/admin-dashboard.png`
- `assets/screenshots/login-admin.png`
- `assets/screenshots/login-technician.png`
- `assets/screenshots/login-customer.png`

## Release Packages Not Exported

- `dist/driftpunkt-install-1.0.10.zip`
