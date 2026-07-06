# Alpine Installation

## Requirements

- Alpine Linux with OpenRC
- Root shell
- At least 512 MB RAM
- Ports 80 and 443 open for website and ACME validation
- `community` repository enabled if your Alpine release ships `acme.sh` there

## Install

Clone the repository, then run the installer from the cloned directory:

```sh
git clone <this-repository-url> lightlnmp
cd lightlnmp
sh install_alpine.sh
```

Install with MariaDB:

```sh
sh install_alpine.sh --with-mariadb
```

Set a custom admin password and panel port:

```sh
sh install_alpine.sh --admin-password 'change-this-password' --panel-port 8888
```

The installer prints the panel URL and credentials when finished.

## Installed Packages

The installer uses `apk` for all system components:

```text
nginx
php php-fpm php-session php-json php-sqlite3 php-pdo_sqlite
php-mbstring php-openssl php-fileinfo php-posix php-curl
phpXY-opcache, only when the matching package exists in the enabled Alpine repositories
sqlite doas curl ca-certificates openssl
acme.sh
mariadb mariadb-client php-mysqli php-pdo_mysql, only with --with-mariadb
```

If `apk add acme.sh` fails, enable the Alpine `community` repository for your release and rerun the installer. This project does not install acme.sh with a curl pipe.

## Paths

Default paths:

```text
/opt/lightlnmp                 Panel files
/www/wwwroot                   Site roots
/etc/lightlnmp/ssl             Installed certificates
/etc/nginx/http.d              Generated Nginx site configs
/opt/lightlnmp/panel/storage   SQLite database and logs
```

## 512 MB Tuning

The default configuration is intentionally small:

- Nginx uses one worker and 512 worker connections.
- PHP-FPM uses `pm = ondemand` and `pm.max_children = 3`.
- PHP memory limit is `96M`.
- MariaDB is optional and uses a small InnoDB buffer pool when installed.

For very small VPS instances, install without MariaDB unless the site needs it.
