# LightLNMP

LightLNMP is a lightweight Alpine Linux web hosting panel using Nginx, PHP-FPM, SQLite, optional MariaDB, and acme.sh.

The panel itself is written in PHP and stores metadata in SQLite. System packages are installed through `apk`.

## Quick Install

```sh
git clone <this-repository-url> lightlnmp
cd lightlnmp
sh install_alpine.sh --with-mariadb
```

For a minimal install without MariaDB:

```sh
sh install_alpine.sh
```

After installation, open:

```text
http://SERVER_IP:8888/
```

See [docs/alpine-install.md](docs/alpine-install.md) and [docs/usage.md](docs/usage.md).

## Update

```sh
git pull
sh update_alpine.sh
```

See [docs/update.md](docs/update.md).
