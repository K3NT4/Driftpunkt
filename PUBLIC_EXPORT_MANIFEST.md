# Public Export Manifest

Created: 2026-11-26T19:15:02+01:00

## Export Policy

- Public exports contain release packages only.
- The latest installation package is included.
- The latest upgrade package is cumulative and should normally be used first.
- Older upgrade packages are retained as fallback and release history.
- Up to the latest 3 upgrade packages are included.
- Selected logo and screenshot assets are included for README presentation.
- License, security policy, issue templates, and the manual release workflow are copied from the development repository.
- Application code and dependencies are included inside the release archives. Private Git history, live environment files, deployment secrets, runtime customer data, and internal planning documents are excluded.

## Exported Packages

| Type | Version | Package | SHA-256 |
| --- | --- | --- | --- |
| install | 2.0.4 | `packages/driftpunkt-install-2.0.4.zip` | `9beeb9cd333e524a474ff69d7181f951c5eaf76d51a38b1c10874bdb48abe3f4` |
| upgrade | 2.0.4 | `packages/driftpunkt-upgrade-2.0.4.zip` | `e4fc09d3f2dc22ec4e0d704ac17cb40a67e3ba2931867a53e350428b341cebc2` |

| upgrade | 2.0.3 | `packages/driftpunkt-upgrade-2.0.3.zip` | `cb36cf2e90432ef25b5ce40d294d1648f810e2c2ad15e59c626fc1845ea27d03` |
| upgrade | 2.0.2 | `packages/driftpunkt-upgrade-2.0.2.zip` | `cef7584d135bdfd761774493aaa3611720a6abd8446dbe41877f5ce28e37bce3` |

## Exported README Assets

- `assets/branding/logo-wide.png`
- `assets/branding/logo-icon.png`
- `assets/branding/logo-wide-v2.svg`
- `assets/branding/logo-wide-v2-dark.svg`
- `assets/branding/driftpunkt-icon-v2.svg`
- `assets/screenshots/homepage.png`
- `assets/screenshots/public-status.png`
- `assets/screenshots/contact-page.png`
- `assets/screenshots/maintenance-mode.png`
- `assets/screenshots/customer-portal.png`
- `assets/screenshots/customer-ticket.png`
- `assets/screenshots/customer-mobile.png`
- `assets/screenshots/technician-portal.png`
- `assets/screenshots/technician-overview.png`
- `assets/screenshots/technician-tickets.png`
- `assets/screenshots/coordinator-portal.png`
- `assets/screenshots/admin-dashboard.png`
- `assets/screenshots/login-admin.png`
- `assets/screenshots/login-technician.png`
- `assets/screenshots/login-customer.png`
