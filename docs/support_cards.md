# Support cards – installation and operation

A company support card provides a QR code and persistent link to a public ticket form. It never grants sign-in or access to existing tickets.

## Prerequisites

- Apply pending migrations before use, particularly `Version20261114190000`.
- Install GD with JPEG, PNG and FreeType in web PHP and scheduler runtimes.
- Set canonical `DRIFTPUNKT_PUBLIC_BASE_URL`, normally HTTPS. An empty address allows application startup but not valid card export.
- Preserve `APP_SECRET` on every upgrade. Encrypted card links and other stored secrets depend on installation keys.

## Permissions and management

An administrator explicitly grants card management to active customer users in the card's company. Ordinary ticket access or subsidiary read access does not automatically confer this permission.

Authorized users can update instructions/contact details and an optional logo, preview saved content, and export PDF/SVG. JPEG/PNG logos are validated and re-encoded; arbitrary external images and uploaded SVG are not accepted.

Saving text preserves the link. **Rotate** replaces it and invalidates old printed cards. **Revoke** disables it. Company deactivation permanently revokes the card; reactivation requires explicit rotation and reprinting. Old user grants are not automatically restored by reactivation.

## Public submission and review

Known active customers in the correct company use normal submission. Unknown or mismatched contacts require internal coordinator review. Pending drafts are not customer-visible, assigned or given SLA/technician notifications. A QR link never automatically creates an account or mail alias.

## Backups and logs

Back up database, `APP_SECRET`, private environment and `var/support_cards/logos` together. Downloaded PDF/SVG cards contain the bearer link and need appropriate confidentiality/backup handling.

Mask `/supportkort/<token>` in every access log, including existing HTTPS hosts and external proxies. Apache examples and application-log masking do not replace verification of your deployment. Never publish a real card link in documentation, screenshots or public issues.
