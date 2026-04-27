# Public Export Manifest

Created: 2026-04-27T17:47:39+00:00

## Export Policy

- Public exports contain release packages only.
- The latest installation package is included.
- Up to the latest 3 upgrade packages are included.
- Application source code, private modules, internal release tooling, environment files, and deployment secrets are intentionally excluded.

## Exported Packages

| Type | Version | Package | SHA-256 |
| --- | --- | --- | --- |
| install | 1.0.9 | `packages/driftpunkt-install-1.0.9.zip` | `f777d13f51306bdeaf6a80bccc3cb57ea2d8b1d509370da0979477982da14c70` |
| upgrade | 1.0.9 | `packages/driftpunkt-upgrade-1.0.9.zip` | `8d939389a46f66a22f435703ca211d42669520a162b832ae3c69581039663210` |
| upgrade | 1.0.8 | `packages/driftpunkt-upgrade-1.0.8.zip` | `4d45b5ae5c30e3e103173997e28de8b7e175d634c4614325d26acd2cd10c1a82` |
| upgrade | 1.0.7 | `packages/driftpunkt-upgrade-1.0.7.zip` | `edbde75e60d356260419a724d14d257ff6b04e0c471af46f04e7b5cf64d60d48` |

## Release Packages Not Exported

- `dist/driftpunkt-install-1.0.8.zip`
