# Updating LightLNMP

Update directly from GitHub on an installed server:

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/updateall.sh | sh
```

Or use the installed updater to fetch the latest source from GitHub:

```sh
sh /opt/lightlnmp/update.sh --from-repo
```

Or run the same repository update through `llctl`:

```sh
/opt/lightlnmp/bin/llctl update from-repo
```

Run the update script from a fresh or updated local repository checkout:

```sh
cd lightlnmp
git pull
sh update.sh
```

If your panel was installed with a custom port or path, pass the same values:

```sh
sh update.sh --panel-port 8888 --install-dir /opt/lightlnmp --web-root /www/wwwroot
```

The repository update mode accepts the same update options:

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/updateall.sh | sh -s -- --panel-port 8888 --no-reload
sh /opt/lightlnmp/update.sh --from-repo --panel-port 8888 --no-reload
/opt/lightlnmp/bin/llctl update from-repo --panel-port 8888 --no-reload
```

## What It Updates

The updater replaces these installed files from the current repository checkout:

```text
/opt/lightlnmp/panel/app
/opt/lightlnmp/panel/public
/opt/lightlnmp/bin
/opt/lightlnmp/config
/opt/lightlnmp/installall.sh
/opt/lightlnmp/install_alpine.sh
/opt/lightlnmp/updateall.sh
/opt/lightlnmp/update.sh
/opt/lightlnmp/update_alpine.sh
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
sh update.sh --no-system-config
```

To skip service reloads:

```sh
sh update.sh --no-reload
```
