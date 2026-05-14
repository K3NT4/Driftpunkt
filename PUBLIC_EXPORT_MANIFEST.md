# Public Export Manifest

Created: 2026-05-14T11:08:07+02:00

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
| install | 1.0.46 | `packages/driftpunkt-install-1.0.46.zip` | `d63ef3bde8ec3878ac7ae3b6b8a832594c3fb9e46ee3c4d13d10509f3851317e` |
| upgrade | 1.0.46 | `packages/driftpunkt-upgrade-1.0.46.zip` | `a54b56d853373a55fb92c86d52394d8d933f7f54e5f1a358b723db166a940264` |
| upgrade | 1.0.45 | `packages/driftpunkt-upgrade-1.0.45.zip` | `27c9854ef6eb731c0a68cbfa35db377eca711fcf22c31e648b0a6554dd4350f7` |
| upgrade | 1.0.44 | `packages/driftpunkt-upgrade-1.0.44.zip` | `40fc44832bff9326625013765822cadda70482fb4a74a28e8bc1db272caed0f8` |

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

- `dist/driftpunkt-install-1.0.43.zip`
- `dist/driftpunkt-install-1.0.44.zip`
- `dist/driftpunkt-install-1.0.45.zip`
- `dist/driftpunkt-upgrade-1.0.43.zip`
