# Public Export Manifest

Created: 2026-05-19T23:55:06+02:00

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
| install | 1.0.52 | `packages/driftpunkt-install-1.0.52.zip` | `c2784ac460251c8212e46dd70e776c4aac44faf493111d83b2d2cb6364a084c7` |
| upgrade | 1.0.52 | `packages/driftpunkt-upgrade-1.0.52.zip` | `dcf3620aadea69b0f2a74a5b6591708be5634d99cab4cce06627c0868e80e48d` |
| upgrade | 1.0.51 | `packages/driftpunkt-upgrade-1.0.51.zip` | `571fd8ef0c5ffa71c901ff3e9c4a4f09c3c5f265c68c6aecf60056aa5898af31` |
| upgrade | 1.0.47 | `packages/driftpunkt-upgrade-1.0.47.zip` | `ab51bc9e7ca3eec902bd480c274d37e588495d4d1e3b5f01f96f34c9cc5a9514` |

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
- `dist/driftpunkt-upgrade-1.0.43.zip`
- `dist/driftpunkt-upgrade-1.0.44.zip`
- `dist/driftpunkt-upgrade-1.0.45.zip`
- `dist/driftpunkt-upgrade-1.0.46.zip`
