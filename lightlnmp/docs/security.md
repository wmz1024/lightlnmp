# Security Notes

## Online PHP Editing

The file manager allows editing `.php` files because this is useful for lightweight hosting. It is also high risk.

Recommended operating rules:

- Use a strong admin password.
- Do not expose the panel port to untrusted networks without a firewall or VPN.
- Keep Alpine packages updated.
- Review `.lightlnmp-backup` files and remove old backups when no longer needed.

## Path Restrictions

The PHP file manager resolves all paths with `realpath()` and only allows access inside the selected site root. It cannot edit the panel directory, system directories, or certificate private key directories through normal panel actions.

## Privileged Operations

The panel runs as the `nginx` user and uses `doas` to call:

```text
/opt/lightlnmp/bin/llctl
```

`llctl` exposes a small command set for Nginx reloads, site config generation, MariaDB actions, and ACME operations. Avoid adding broad shell execution features to the panel.

## ACME Private Keys

Installed certificates live in:

```text
/etc/lightlnmp/ssl
```

Private keys should not be made writable by site users. If you move the SSL root, preserve restrictive permissions.

## MariaDB

The first version manages database creation and local users only. It does not include a SQL console. This is intentional to reduce risk and memory usage.

## Updates

Update Alpine packages regularly:

```sh
apk update
apk upgrade
```

Restart services after package updates when needed:

```sh
rc-service php-fpm85 restart
rc-service nginx restart
```

The exact PHP-FPM service name depends on the Alpine PHP version. On Alpine 3.24 with PHP 8.5 it is usually `php-fpm85`.
