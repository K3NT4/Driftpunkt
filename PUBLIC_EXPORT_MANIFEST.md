# Public Export Manifest

Created: 2026-05-25T20:32:38+02:00

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
| install | 1.0.68 | `packages/driftpunkt-install-1.0.68.zip` | `1a3837f69d8b3412d22c37e21786d8ba08cb1f14beabdb4199f577f9728d61f9` |
| upgrade | 1.0.68 | `packages/driftpunkt-upgrade-1.0.68.zip` | `506872684d9ebcda9e4df1a94e790af3cdf8145b849d8a8d83181e33c28d8eae` |
| upgrade | 1.0.67 | `packages/driftpunkt-upgrade-1.0.67.zip` | `3a63f77e0d45c8cccf0e5dea39ec9067bb9737e2533fac701f39435395c26324` |
| upgrade | 1.0.63 | `packages/driftpunkt-upgrade-1.0.63.zip` | `0ef7eef2645175f149a89bf6dd1c4dfd828fc586d12600c4dfb1d6235788a32c` |

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
- `dist/driftpunkt-install-1.0.63.zip`
- `dist/driftpunkt-install-1.0.67.zip`
- `dist/driftpunkt-upgrade-1.0.43.zip`
- `dist/driftpunkt-upgrade-1.0.44.zip`
- `dist/driftpunkt-upgrade-1.0.45.zip`
- `dist/driftpunkt-upgrade-1.0.46.zip`
- `dist/driftpunkt-upgrade-1.0.47.zip`
- `dist/driftpunkt-upgrade-1.0.51.zip`
- `dist/driftpunkt-upgrade-1.0.52.zip`
- `dist/driftpunkt-upgrade-1.0.53.zip`
