# Public Export Manifest

Created: 2026-05-21T21:58:11+02:00

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
| install | 1.0.63 | `packages/driftpunkt-install-1.0.63.zip` | `803b426d75a813b3ce7857b574c4df497e54215a8f852defb25c6db83598d3a3` |
| upgrade | 1.0.63 | `packages/driftpunkt-upgrade-1.0.63.zip` | `0ef7eef2645175f149a89bf6dd1c4dfd828fc586d12600c4dfb1d6235788a32c` |
| upgrade | 1.0.53 | `packages/driftpunkt-upgrade-1.0.53.zip` | `ab7fefb6bb195c494907dee826d56cf81f17285eaf12a84456051fd9577ef669` |
| upgrade | 1.0.52 | `packages/driftpunkt-upgrade-1.0.52.zip` | `601698c59bdd9103a053b104333a6a7b553fd73354ff78c29e6c94ea1b6aa703` |

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
- `dist/driftpunkt-install-1.0.46.zip`
- `dist/driftpunkt-install-1.0.47.zip`
- `dist/driftpunkt-install-1.0.51.zip`
- `dist/driftpunkt-install-1.0.52.zip`
- `dist/driftpunkt-install-1.0.53.zip`
- `dist/driftpunkt-upgrade-1.0.43.zip`
- `dist/driftpunkt-upgrade-1.0.44.zip`
- `dist/driftpunkt-upgrade-1.0.45.zip`
- `dist/driftpunkt-upgrade-1.0.46.zip`
- `dist/driftpunkt-upgrade-1.0.47.zip`
- `dist/driftpunkt-upgrade-1.0.51.zip`
