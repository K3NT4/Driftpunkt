# Security Policy

## Supported Versions

Driftpunkt is actively developed and distributed through official installation and upgrade packages.

Security updates are generally provided for the latest available version.

| Version | Security Updates |
|---|---|
| Latest release | Supported |
| Older releases | Not guaranteed |

Users are strongly encouraged to keep their installations updated and follow the security instructions included in the release documentation.

## Reporting a Vulnerability

Security is important to the Driftpunkt project.

If you discover a potential security vulnerability, please report it privately.

**Security contact:** kenta[a]lumirinav.se

**GitHub:** https://github.com/K3NT4/Driftpunkt

Please do not report security vulnerabilities through public GitHub Issues.

### What to Include

When reporting a vulnerability, please provide:

- A description of the vulnerability.
- The affected Driftpunkt version.
- The affected feature or component.
- Steps to reproduce the issue.
- The potential security impact.
- Relevant logs or screenshots with sensitive information removed.
- Suggested mitigation, if available.

Please do not include real passwords, API keys, customer data, authentication tokens, or other confidential information.

## Responsible Disclosure

We ask security researchers and users to:

- Report vulnerabilities privately before public disclosure.
- Avoid accessing, modifying, or deleting data belonging to others.
- Avoid testing against installations without authorization.
- Avoid activities that may disrupt production systems.
- Allow reasonable time for investigation and remediation.

Reporting a vulnerability does not authorize testing of third-party systems or access to data without permission.

## Security Response Process

Security reports will be reviewed and prioritized based on their potential impact.

When a vulnerability is confirmed, the project may:

1. Investigate the affected components and versions.
2. Develop and test a security fix.
3. Release an updated installation or upgrade package.
4. Publish relevant security information and mitigation instructions when appropriate.

Response and resolution times are not guaranteed.

## Security Best Practices

Driftpunkt administrators should:

- Install official releases from the project repository.
- Verify package SHA-256 checksums.
- Keep PHP, the operating system, databases, and dependencies updated.
- Use HTTPS for production installations.
- Enable MFA for privileged accounts.
- Use strong passwords and restrict administrative access.
- Maintain secure, regularly tested backups.
- Protect environment files, application secrets, and database credentials.
- Review user permissions and remove unused accounts.
- Monitor logs and background jobs for suspicious behavior.

## Third-Party Dependencies

Driftpunkt uses third-party software components that are governed by their respective licenses and security policies.

Administrators should also monitor security advisories for the underlying runtime and infrastructure.

## Scope

This policy covers vulnerabilities affecting official Driftpunkt application releases.

It does not grant permission to test installations operated by other users or organizations.

## Contact

**Project:** Driftpunkt  
**Maintainer:** Kenta Mattsson  
**Email:** kenta[a]lumirinav.se

---

Copyright © 2026 Kenta Mattsson. All rights reserved.

Driftpunkt is distributed under the Driftpunkt Free Use License.
