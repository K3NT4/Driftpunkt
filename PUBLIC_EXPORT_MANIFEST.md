# Public Export Manifest

Created: 2026-05-14T13:27:51+02:00

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
| install | 1.0.47 | `packages/driftpunkt-install-1.0.47.zip` | `6b20e51bee8a16edf7868fcf732e50174d0c055b9714bb28c7921cc1fe2a55f3` |
| upgrade | 1.0.47 | `packages/driftpunkt-upgrade-1.0.47.zip` | `ab51bc9e7ca3eec902bd480c274d37e588495d4d1e3b5f01f96f34c9cc5a9514` |
| upgrade | 1.0.46 | `packages/driftpunkt-upgrade-1.0.46.zip` | `a54b56d853373a55fb92c86d52394d8d933f7f54e5f1a358b723db166a940264` |
| upgrade | 1.0.45 | `packages/driftpunkt-upgrade-1.0.45.zip` | `27c9854ef6eb731c0a68cbfa35db377eca711fcf22c31e648b0a6554dd4350f7` |

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
- `dist/driftpunkt-upgrade-1.0.43.zip`
- `dist/driftpunkt-upgrade-1.0.44.zip`
