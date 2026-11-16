# Start here – install Driftpunkt

This guide is for a **new installation** from the installation archive. Unpack the ZIP and enter the directory containing `composer.json`, `Dockerfile` and `deploy/`. Keep hidden files, especially `.dockerignore`. No real user accounts or demo tickets are created automatically.

Already running Driftpunkt with existing data? Follow the [2.0.1 upgrade guide](docs/upgrade_to_2_0_1.md), not a fresh installation or `app:install:fresh`.

After installation, see [custom addons](docs/addons.md) for data-only panels and **Features** for the built-in news-editor and telephone-support switches.

## NAS with Docker / Container Manager

Requirements: an SSH terminal, Docker and Docker Compose v2. Put `sudo` before `bash` if Docker requires it. Run these commands from the unpacked package root:

```bash
bash deploy/nas/manage.sh init http://YOUR-NAS-IP:8080
bash deploy/nas/manage.sh install
bash deploy/nas/manage.sh admin your-email@example.com
```

1. **init** creates a private environment file with random secrets and matching database credentials. It never overwrites an existing file. Use the address you will actually access. The default NAS port is 8080; change `APP_PORT` in `deploy/nas/.env` if occupied. Behind a reverse proxy, use the public HTTPS address while `APP_PORT` remains the internal host port.
2. **install** builds the image, waits for the database, starts the app and initializes an empty database. The scheduler is started later. Existing database files stop a new install before startup; explicitly use `install --resume` for an interrupted new installation. Database initialization always rejects existing tables.
3. **admin** prompts for a password without echoing it. Use at least 12 characters. Never put passwords in command-line arguments.

Open the address, sign in and verify the first account. Configure SMTP and inboxes only when ready to permit real messages. Then run:

```bash
bash deploy/nas/manage.sh check
bash deploy/nas/manage.sh start
bash deploy/nas/manage.sh status
```

`check` should finish without errors (`FEL` in the current helper output). `start` enables recurring business jobs. The helper does not change firewall rules, port forwarding or TLS. Use HTTPS before introducing real users and data. HTTP/IP is suitable only for isolated temporary tests.

[Full NAS guide and backup](docs/nas_docker_setup.md). Protect `deploy/nas/.env`, data and logos. **Do not use web-based code replacement with the supplied image-based Compose stack**; update both app and scheduler images as described in the guide. Older custom stacks sharing the whole code directory use a different deployment model.

## Debian 13 without Docker

Requirements: root/sudo, a new or inventoried server, and the installation package under a directory such as `/var/www/driftpunkt`.

1. **Prepare the server.** Replace the domain with your own. The command installs system packages and prepares a missing environment file with a random `APP_SECRET`:

   ```bash
   cd /var/www/driftpunkt
   sudo env DOMAIN=support.example.com bash deploy/debian/setup.sh
   ```

   If database configuration is missing, the script stops with the next action; no tables or accounts are created. For an isolated IP-based test, use `DOMAIN=YOUR-SERVER-IP PUBLIC_URL=http://YOUR-SERVER-IP`.

2. **Configure the database and environment.** Follow the [Debian database step](docs/debian_server_setup.md#quick-installation). Set the database password in MariaDB and use the same value in `.env.local`. Preserve the generated `APP_SECRET`. Run the same setup command again. It installs dependencies, the virtual host and timer files but does not start business jobs.
3. **Initialize the empty database and create the super administrator:**

   ```bash
   sudo -u www-data php bin/console app:install:fresh --env=prod
   sudo -u www-data php bin/console app:create-admin your-email@example.com --type=super_admin --env=prod
   ```

   The password prompt is hidden. Initialization rejects existing tables.
4. **Verify, enable HTTPS and start jobs** using the [Debian guide](docs/debian_server_setup.md). Test sign-in, protect the account with MFA and check both PHP runtimes. `bash deploy/debian/setup.sh --check` performs read-only local PHP/file checks.

PHP must meet the package's exact requirements, including GD with JPEG/PNG/FreeType. The scripts stop if PHP is too old; they do not silently add third-party package repositories. Debian 13 uses distribution packages and its security updates.

## If something stops

- Read the first error, not only the last line. Do not initialize a database containing existing data.
- NAS: `bash deploy/nas/manage.sh status` and `bash deploy/nas/manage.sh logs`.
- Debian: `bash deploy/debian/setup.sh --check` and `sudo apache2ctl configtest`.
- Never post `.env.local`, `deploy/nas/.env`, passwords, tokens or backup contents in public issues.
- First installation, upgrade and restoration are separate operations. Neither helper deletes an existing database to start over.
