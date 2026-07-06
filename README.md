# LightLNMP

LightLNMP is a lightweight Alpine Linux web hosting panel using Nginx, PHP-FPM, SQLite, optional MariaDB, and acme.sh.

The panel itself is written in PHP and stores metadata in SQLite. System packages are installed through `apk`.

## Quick Install

One-command install from GitHub:

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/installall.sh | sh
```

Install with MariaDB:

```sh
wget -O - https://raw.githubusercontent.com/wmz1024/lightlnmp/master/installall.sh | sh -s -- --with-mariadb
```

Manual checkout install:

```sh
git clone https://github.com/wmz1024/lightlnmp.git lightlnmp
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

Update directly from GitHub on an installed server:

```sh
sh /opt/lightlnmp/update.sh --from-repo
```

Or through the privileged control helper:

```sh
/opt/lightlnmp/bin/llctl update from-repo
```

Update from a local checkout:

```sh
git pull
sh update.sh
```

See [docs/update.md](docs/update.md).
