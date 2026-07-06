# Updating LightLNMP

Run the update script from a fresh or updated repository checkout:

```sh
cd lightlnmp
sh update_alpine.sh
```

If your panel was installed with a custom port or path, pass the same values:

```sh
sh update_alpine.sh --panel-port 8888 --install-dir /opt/lightlnmp --web-root /www/wwwroot
```

## What It Updates

The updater replaces these installed files from the current repository checkout:

```text
/opt/lightlnmp/panel/app
/opt/lightlnmp/panel/public
/opt/lightlnmp/bin
/opt/lightlnmp/config
```

It preserves runtime data:

```text
/opt/lightlnmp/panel/storage
/www/wwwroot
/etc/lightlnmp/ssl
```

Before making changes, it creates a tar backup under:

```text
/opt/lightlnmp.backups
```

## System Configs

By default, the updater reapplies LightLNMP's panel-related Nginx, PHP-FPM, doas, and ACME cron configuration. This is useful when an update fixes service names or runtime paths.

To update only panel files and skip system configuration rewrites:

```sh
sh update_alpine.sh --no-system-config
```

To skip service reloads:

```sh
sh update_alpine.sh --no-reload
```
