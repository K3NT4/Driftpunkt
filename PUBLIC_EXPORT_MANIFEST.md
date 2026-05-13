# Public Export Manifest

Created: 2026-05-13T18:28:50+02:00

## Export Policy

- Public exports contain release packages only.
- The latest installation package is included.
- The latest upgrade package is cumulative and should normally be used first.
- Older upgrade packages are retained as fallback and release history.
- Up to the latest 3 upgrade packages are included.
- Selected logo and screenshot assets are included for README presentation.
- Application source code, private modules, internal release tooling, environment files, and deployment secrets are intentionally excluded.

## Exported Packages

| Type | Version | Package | SHA-256 |
| --- | --- | --- | --- |
| install | 1.0.43 | `packages/driftpunkt-install-1.0.43.zip` | `db4a6dfaca15e14e26f972b96e202ecec52be163dd105a6b6e1c1173860e9403` |
| upgrade | 1.0.43 | `packages/driftpunkt-upgrade-1.0.43.zip` | `5d2f93e51812b0911c67ad3a0d0259d1c6348a30dfa3f0595fc56da195f89f54` |

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
