# Create an addon in Driftpunkt 2.0.1

An addon is a small JSON file containing an information panel and links. You do not need PHP, a build tool or access to Driftpunkt's source code.

## Start in the administration interface

1. A **super administrator** opens **Addons → Custom addons** and downloads the example JSON.
2. Edit `slug`, `name`, `description`, `placement` and `links` in a text editor. Save as UTF-8 JSON.
3. Upload the file and choose **Validate** or **Preview**. These actions do not save or activate it.
4. **Import** the version. Import leaves it inactive.
5. Check the content and audience, then explicitly **Activate** the version.

The interface supports Swedish, English and Norwegian. Your own text is displayed as supplied; it is not automatically translated.

## Example

```json
{
  "schema_version": 1,
  "slug": "service-information",
  "version": "1.0.0",
  "name": "Service information",
  "description": "Find our support opening hours and planned maintenance.",
  "placement": "homepage",
  "links": [
    {"label": "Service status", "url": "/status"}
  ]
}
```

See the [downloadable example](examples/addon.json) and [JSON schema](addon.schema.json). Use links that exist in your installation.

| Placement | Audience |
| --- | --- |
| `homepage` | Public homepage, including visitors who are not signed in |
| `customer_dashboard` | Customer overview, shared by customer accounts across companies |
| `technician_dashboard` | Technician dashboard in modern and classic layouts |

Panels are shared content for a placement. Do not put customer-specific, ticket-specific or confidential information in them. A placement does not provide access to customer or ticket data.

## Format and boundaries

- `schema_version` is the integer `1`. Unknown keys are rejected.
- `slug` uses lowercase letters, digits and hyphens, begins with a letter and is at most 64 characters.
- `version` has three numeric parts, for example `1.0.1`.
- Maximum file size: 32 KiB. Maximum name length: 80 characters; description: 1,000 characters.
- Up to eight links, with labels up to 80 characters and URLs up to 2,048 characters.
- Links allow an internal path beginning with a single `/` or an HTTPS URL without embedded credentials. Unsafe schemes, control characters and backslashes are rejected.
- Text is escaped. HTML and Twig-like expressions remain text. PHP, JavaScript, SQL, templates, uploaded assets and background jobs are not supported.
- The application does not fetch addon URLs. An addon receives no database, API or server network access.

## Update, switch back or remove

Keep the slug and increase the version. Importing a newer version preserves the active version until you explicitly select another. Existing version numbers cannot be overwritten. Select a retained version to switch back, or disable the panel. An active version must be disabled before deletion. Storage allows up to 100 manifest versions in total.

## CLI for operators

Run from the installed application directory:

```bash
php bin/console app:addon:create service-information --output=addon.json
php bin/console app:addon:validate addon.json
```

Creation refuses to overwrite an existing file. Validation does not import or activate anything. These commands use the permissions of the operator running them; ordinary authors can use the web example instead.

## Storage and recovery

Manifest versions and activation choices are stored in `var/declarative_addons/`. Include the entire directory in backups, keep it outside the webroot and allow the application user to read and write it. Symlinked storage is rejected. The admin interface reports invalid manifests, broken activation state and unavailable storage with recovery guidance. Invalid panels are omitted from public rendering.

The previous executable ZIP addon model is retired. Use this JSON format for new panels. **Features** contains the switches for extended news editing and telephone support. SupportDesk and its AnyDesk/TeamViewer shortcuts have been removed; their historical records are retained.
