# Usage

## Login

Open the panel URL printed by the installer:

```text
http://SERVER_IP:8888/
```

Use the generated admin credentials or the credentials passed to `install_alpine.sh`.

## Sites

The Sites page can create, enable, disable, and delete sites.

Site names are limited to letters, numbers, dots, underscores, and hyphens. Each site gets a root directory under `/www/wwwroot` by default.

Deleting a site removes its Nginx config and site files.

## File Manager

The file manager can:

- Browse site directories
- Upload files
- Download files
- Create files and directories
- Delete files or empty directories
- Edit text files, including `.php`

The editor is intentionally simple and uses a textarea to keep the panel lightweight. Before saving a file, the previous copy is written to `.lightlnmp-backup` under the site root.

## Databases

The database page supports:

- Listing databases
- Creating databases
- Deleting databases
- Creating local database users
- Granting a user access to a database

MariaDB must be installed with `--with-mariadb`. This page is not intended to replace phpMyAdmin.

## SSL Certificates

The SSL page uses acme.sh and Let's Encrypt.

Domain certificates use HTTP webroot validation:

```text
/.well-known/acme-challenge/
```

The panel can issue one certificate for a site identifier and install it into:

```text
/etc/lightlnmp/ssl/SITE/fullchain.pem
/etc/lightlnmp/ssl/SITE/privkey.pem
```

The daily cron job runs renewal through:

```text
/etc/periodic/daily/lightlnmp-acme-renew
```

## IP Certificates

Public IP certificates are supported through Let's Encrypt short-lived certificates.

Important limits:

- Only public IPv4 or IPv6 addresses are accepted.
- Private, reserved, loopback, and link-local addresses are rejected by the panel.
- DNS validation cannot be used for IP certificates.
- HTTP validation requires port 80 to reach this server.
- IP certificates use the `shortlived` profile and renew more frequently.

## Services

The Services page can show status and start, stop, or restart:

```text
nginx
php-fpm
mariadb
crond
```
