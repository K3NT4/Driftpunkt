# Public Export Manifest

Created: 2026-05-26T18:46:25+02:00

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
| install | 1.0.70 | `packages/driftpunkt-install-1.0.70.zip` | `a4f00217bc6aa9af7b7a0be2cf0394e19c9fe4b58247e839497732a46f00bc42` |
| upgrade | 1.0.70 | `packages/driftpunkt-upgrade-1.0.70.zip` | `eb0a133a49a131a15e8f2d344fd1068bb0aec4c024ddefe189db46225229c4dc` |
| upgrade | 1.0.68 | `packages/driftpunkt-upgrade-1.0.68.zip` | `506872684d9ebcda9e4df1a94e790af3cdf8145b849d8a8d83181e33c28d8eae` |
| upgrade | 1.0.67 | `packages/driftpunkt-upgrade-1.0.67.zip` | `3a63f77e0d45c8cccf0e5dea39ec9067bb9737e2533fac701f39435395c26324` |

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
- `dist/driftpunkt-install-1.0.68.zip`
- `dist/driftpunkt-upgrade-1.0.43.zip`
- `dist/driftpunkt-upgrade-1.0.44.zip`
- `dist/driftpunkt-upgrade-1.0.45.zip`
- `dist/driftpunkt-upgrade-1.0.46.zip`
- `dist/driftpunkt-upgrade-1.0.47.zip`
- `dist/driftpunkt-upgrade-1.0.51.zip`
- `dist/driftpunkt-upgrade-1.0.52.zip`
- `dist/driftpunkt-upgrade-1.0.53.zip`
- `dist/driftpunkt-upgrade-1.0.63.zip`
