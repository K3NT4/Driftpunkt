# Public Export Manifest

Created: 2026-05-20T02:10:58+02:00

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
| install | 1.0.53 | `packages/driftpunkt-install-1.0.53.zip` | `94bb4992cc942f668a2ca590983918c456143dbc2b1d5ad4040992012262a8fe` |
| upgrade | 1.0.53 | `packages/driftpunkt-upgrade-1.0.53.zip` | `ab7fefb6bb195c494907dee826d56cf81f17285eaf12a84456051fd9577ef669` |
| upgrade | 1.0.52 | `packages/driftpunkt-upgrade-1.0.52.zip` | `601698c59bdd9103a053b104333a6a7b553fd73354ff78c29e6c94ea1b6aa703` |
| upgrade | 1.0.51 | `packages/driftpunkt-upgrade-1.0.51.zip` | `571fd8ef0c5ffa71c901ff3e9c4a4f09c3c5f265c68c6aecf60056aa5898af31` |

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
- `dist/driftpunkt-upgrade-1.0.43.zip`
- `dist/driftpunkt-upgrade-1.0.44.zip`
- `dist/driftpunkt-upgrade-1.0.45.zip`
- `dist/driftpunkt-upgrade-1.0.46.zip`
- `dist/driftpunkt-upgrade-1.0.47.zip`
