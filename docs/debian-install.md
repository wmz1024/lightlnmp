# Debian Installation

## Requirements

- Debian 12 with systemd
- Root shell
- At least 512 MB RAM
- Ports 80 and 443 open for website and ACME validation

## Install

Run the one-command installer from GitHub:

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/installall.sh | sh
```

Install with MariaDB:

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/installall.sh | sh -s -- --with-mariadb
```

Set a custom admin password and panel port:

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/installall.sh | sh -s -- --admin-password 'change-this-password' --panel-port 8888
```

Alternatively, clone the repository, then run the Debian installer from the cloned directory:

```sh
git clone https://github.com/wmz1024/lightlnmp.git lightlnmp
cd lightlnmp
sh install_debian.sh
```

Manual checkout install with MariaDB:

```sh
sh install_debian.sh --with-mariadb
```

The installer prints the panel URL and credentials when finished.

## Installed Packages

The installer uses `apt` for system components:

```text
nginx opendoas sqlite3 curl ca-certificates openssl git cron
php-cli php-fpm php-sqlite3 php-mbstring php-curl php-zip php-xml php-mysql
acme.sh, when available in enabled apt repositories
mariadb-server mariadb-client, only with --with-mariadb
```

If `acme.sh` is not available in the enabled apt repositories, the installer continues and SSL issuance remains unavailable until `acme.sh` is installed.

## Paths

Default paths:

```text
/opt/lightlnmp                 Panel files
/www/wwwroot                   Site roots
/etc/lightlnmp/ssl             Installed certificates
/etc/lightlnmp/rewrite         Generated rewrite rule snippets
/etc/nginx/http.d              Generated Nginx site configs
/opt/lightlnmp/panel/storage   SQLite database and logs
```

## Debian Notes

- PHP-FPM is detected from `/etc/php/*/fpm/pool.d` and systemd unit names such as `php8.2-fpm`.
- The panel runs as `www-data`.
- The privileged helper uses `opendoas` and writes a managed block to `/etc/doas.conf`.
- Nginx is configured to include `/etc/nginx/http.d/*.conf`.
