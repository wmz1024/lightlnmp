#!/bin/sh
set -eu

APP_NAME="lightlnmp"
INSTALL_DIR="/opt/lightlnmp"
WEB_ROOT="/www/wwwroot"
SSL_ROOT="/etc/lightlnmp/ssl"
PANEL_PORT="8888"
PANEL_USER="nginx"
WITH_MARIADB="0"
ADMIN_USER="admin"
ADMIN_PASSWORD=""

usage() {
    cat <<USAGE
Usage: sh install_alpine.sh [options]

Options:
  --with-mariadb              Install and initialize MariaDB
  --admin-user USER           Default: admin
  --admin-password PASSWORD   If omitted, a random password is generated
  --panel-port PORT           Default: 8888
  --install-dir PATH          Default: /opt/lightlnmp
  --web-root PATH             Default: /www/wwwroot
  -h, --help                  Show this help
USAGE
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --with-mariadb) WITH_MARIADB="1" ;;
        --admin-user) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; ADMIN_USER="$2"; shift ;;
        --admin-password) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; ADMIN_PASSWORD="$2"; shift ;;
        --panel-port) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; PANEL_PORT="$2"; shift ;;
        --install-dir) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; INSTALL_DIR="$2"; shift ;;
        --web-root) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; WEB_ROOT="$2"; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
    esac
    shift
done

case "$ADMIN_USER" in
    *[!A-Za-z0-9_.-]*|'') echo "Invalid admin user." >&2; exit 1 ;;
esac

case "$PANEL_PORT" in
    *[!0-9]*|'') echo "Invalid panel port." >&2; exit 1 ;;
esac

if [ "$PANEL_PORT" -lt 1 ] || [ "$PANEL_PORT" -gt 65535 ]; then
    echo "Panel port must be between 1 and 65535." >&2
    exit 1
fi

require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        echo "This installer must be run as root." >&2
        exit 1
    fi
}

require_alpine() {
    if [ ! -f /etc/alpine-release ]; then
        echo "This installer currently supports Alpine Linux only." >&2
        exit 1
    fi
    if ! command -v rc-service >/dev/null 2>&1; then
        echo "OpenRC was not found. This installer expects Alpine with OpenRC." >&2
        exit 1
    fi
}

apk_install() {
    cleanup_opcache_world
    apk update
    apk add --no-cache \
        nginx doas sqlite curl ca-certificates openssl shadow \
        php php-fpm php-session php-json php-sqlite3 php-pdo_sqlite \
        php-mbstring php-openssl php-fileinfo php-posix php-curl php-ctype php-tokenizer

    install_php_opcache
    install_php_extension zip
    install_php_extension zlib

    if [ "$WITH_MARIADB" = "1" ]; then
        apk add --no-cache mariadb mariadb-client php-mysqli php-pdo_mysql
    fi

    if ! apk add --no-cache acme.sh; then
        cat >&2 <<'MSG'
Failed to install acme.sh from apk repositories.
Enable Alpine community repository for your release, then rerun this installer.
This project intentionally does not install acme.sh through curl-based upstream scripts.
MSG
        exit 1
    fi
}

cleanup_opcache_world() {
    if [ -f /etc/apk/world ]; then
        sed -i '/^php-opcache$/d;/^php[0-9][0-9]-opcache$/d' /etc/apk/world 2>/dev/null || true
    fi
}

detect_php_conf_dir() {
    php -r 'echo dirname(php_ini_loaded_file());' 2>/dev/null || true
}

detect_php_suffix() {
    php -r 'echo PHP_MAJOR_VERSION . PHP_MINOR_VERSION;' 2>/dev/null || true
}

install_php_opcache() {
    php_suffix=$(detect_php_suffix)
    if [ -z "$php_suffix" ]; then
        echo "Notice: unable to detect PHP version; continuing without OPcache." >&2
        return 0
    fi

    pkg="php${php_suffix}-opcache"
    if apk search -x "$pkg" | grep -q "^$pkg-"; then
        apk add --no-cache "$pkg" || echo "Notice: failed to install $pkg; continuing without OPcache." >&2
    else
        echo "Notice: $pkg is not available in the enabled repositories; continuing without OPcache." >&2
    fi
}

install_php_extension() {
    ext="$1"
    if php -m 2>/dev/null | grep -qi "^$ext$"; then
        return 0
    fi

    if apk add --no-cache "php-$ext" >/dev/null 2>&1; then
        return 0
    fi

    php_suffix=$(detect_php_suffix)
    if [ -n "$php_suffix" ]; then
        pkg="php${php_suffix}-$ext"
        if apk search -x "$pkg" | grep -q "^$pkg-"; then
            apk add --no-cache "$pkg" || echo "Notice: failed to install $pkg; archive extraction may be limited." >&2
            return 0
        fi
    fi

    echo "Notice: php-$ext is not available; archive extraction may be limited." >&2
}

detect_php_fpm_service() {
    php_suffix=$(detect_php_suffix)
    if [ -n "$php_suffix" ] && [ -f "/etc/init.d/php-fpm$php_suffix" ]; then
        echo "php-fpm$php_suffix"
        return 0
    fi
    for svc in php-fpm85 php-fpm84 php-fpm83 php-fpm82 php-fpm81 php-fpm php85-fpm php84-fpm php83-fpm php82-fpm php81-fpm; do
        if [ -f "/etc/init.d/$svc" ]; then
            echo "$svc"
            return 0
        fi
    done
    echo "php-fpm"
}

render_template() {
    src="$1"
    dst="$2"
    sed \
        -e "s#__INSTALL_DIR__#$INSTALL_DIR#g" \
        -e "s#__WEB_ROOT__#$WEB_ROOT#g" \
        -e "s#__SSL_ROOT__#$SSL_ROOT#g" \
        -e "s#__PANEL_PORT__#$PANEL_PORT#g" \
        -e "s#__PANEL_USER__#$PANEL_USER#g" \
        "$src" > "$dst"
}

copy_project() {
    src_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
    mkdir -p "$INSTALL_DIR"
    cp -R "$src_dir/panel" "$INSTALL_DIR/"
    cp -R "$src_dir/bin" "$INSTALL_DIR/"
    cp -R "$src_dir/config" "$INSTALL_DIR/"
    for file in installall.sh install_alpine.sh updateall.sh update.sh update_alpine.sh; do
        if [ -f "$src_dir/$file" ]; then
            cp "$src_dir/$file" "$INSTALL_DIR/$file"
        fi
    done
    chmod 0755 "$INSTALL_DIR/bin/llctl"
    chmod 0755 "$INSTALL_DIR"/*.sh 2>/dev/null || true
}

configure_dirs() {
    mkdir -p "$WEB_ROOT" "$SSL_ROOT" "$INSTALL_DIR/panel/storage/logs"
    chown -R "$PANEL_USER:$PANEL_USER" "$WEB_ROOT" "$INSTALL_DIR/panel/storage"
    chmod 0750 "$INSTALL_DIR/panel/storage"
    chmod 0750 "$SSL_ROOT"
}

configure_doas() {
    mkdir -p /etc/doas.d
    cat > /etc/doas.d/lightlnmp.conf <<EOF
permit nopass $PANEL_USER as root cmd $INSTALL_DIR/bin/llctl
EOF
    chmod 0600 /etc/doas.d/lightlnmp.conf
}

configure_php_fpm() {
    php_conf_dir=$(detect_php_conf_dir)
    if [ -z "$php_conf_dir" ] || [ ! -d "$php_conf_dir" ]; then
        echo "Unable to detect PHP configuration directory." >&2
        exit 1
    fi

    pool_dir="$php_conf_dir/php-fpm.d"
    mkdir -p "$pool_dir"
    render_template "$INSTALL_DIR/config/php/lightlnmp.conf.tpl" "$pool_dir/lightlnmp.conf"

    if [ -d "$php_conf_dir/conf.d" ]; then
        render_template "$INSTALL_DIR/config/php/99-lightlnmp.ini.tpl" "$php_conf_dir/conf.d/99-lightlnmp.ini"
    fi
}

configure_nginx() {
    mkdir -p /etc/nginx/http.d
    stop_legacy_nginx
    cp "$INSTALL_DIR/config/nginx/nginx.conf.tpl" /etc/nginx/nginx.conf
    render_template "$INSTALL_DIR/config/nginx/panel.conf.tpl" /etc/nginx/http.d/lightlnmp-panel.conf
    if [ -f /etc/nginx/http.d/default.conf ]; then
        mv /etc/nginx/http.d/default.conf /etc/nginx/http.d/default.conf.disabled
    fi
}

stop_legacy_nginx() {
    if [ -f /run/nginx.pid ]; then
        nginx -s quit >/dev/null 2>&1 || true
        sleep 1
        rm -f /run/nginx.pid
    fi

    if pidof nginx >/dev/null 2>&1 && ! rc-service nginx status >/dev/null 2>&1; then
        for pid in $(pidof nginx); do
            kill -QUIT "$pid" 2>/dev/null || true
        done
        sleep 1
    fi
}

configure_mariadb() {
    if [ "$WITH_MARIADB" != "1" ]; then
        return 0
    fi

    mkdir -p /etc/my.cnf.d
    cp "$INSTALL_DIR/config/mariadb/lightlnmp.cnf.tpl" /etc/my.cnf.d/lightlnmp.cnf

    if [ ! -d /var/lib/mysql/mysql ]; then
        mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
    fi
    rc-update add mariadb default || true
    rc-service mariadb start || true
}

init_panel_db() {
    db="$INSTALL_DIR/panel/storage/panel.sqlite"
    mkdir -p "$(dirname "$db")"
    if [ -z "$ADMIN_PASSWORD" ]; then
        ADMIN_PASSWORD=$(openssl rand -base64 18 | tr -d '\n')
    fi
    password_hash=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$ADMIN_PASSWORD")

    sqlite3 "$db" <<SQL
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS sites (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  root TEXT NOT NULL,
  enabled INTEGER NOT NULL DEFAULT 1,
  force_https INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS domains (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  site_id INTEGER NOT NULL,
  domain TEXT NOT NULL,
  is_primary INTEGER NOT NULL DEFAULT 0,
  UNIQUE(site_id, domain)
);
CREATE TABLE IF NOT EXISTS certificates (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  site_id INTEGER NOT NULL,
  identifier TEXT NOT NULL,
  identifier_type TEXT NOT NULL,
  ca TEXT NOT NULL DEFAULT 'letsencrypt',
  status TEXT NOT NULL DEFAULT 'unknown',
  expires_at TEXT,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(site_id, identifier)
);
CREATE TABLE IF NOT EXISTS audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  actor TEXT NOT NULL,
  action TEXT NOT NULL,
  target TEXT NOT NULL,
  result TEXT NOT NULL,
  detail TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO users(username, password_hash) VALUES('$ADMIN_USER', '$password_hash')
  ON CONFLICT(username) DO UPDATE SET password_hash = excluded.password_hash;
INSERT OR REPLACE INTO settings(key, value) VALUES('install_dir', '$INSTALL_DIR');
INSERT OR REPLACE INTO settings(key, value) VALUES('web_root', '$WEB_ROOT');
INSERT OR REPLACE INTO settings(key, value) VALUES('ssl_root', '$SSL_ROOT');
INSERT OR REPLACE INTO settings(key, value) VALUES('panel_port', '$PANEL_PORT');
INSERT OR REPLACE INTO settings(key, value) VALUES('php_fpm_service', '$(detect_php_fpm_service)');
SQL
    chown "$PANEL_USER:$PANEL_USER" "$db"
    chmod 0640 "$db"
}

configure_acme_cron() {
    mkdir -p /etc/periodic/daily
    cat > /etc/periodic/daily/lightlnmp-acme-renew <<EOF
#!/bin/sh
$INSTALL_DIR/bin/llctl ssl renew-all >> $INSTALL_DIR/panel/storage/logs/acme-renew.log 2>&1
EOF
    chmod 0755 /etc/periodic/daily/lightlnmp-acme-renew
    rc-update add crond default || true
}

start_services() {
    php_fpm_service=$(detect_php_fpm_service)
    rc-update add nginx default || true
    rc-update add "$php_fpm_service" default || true
    rc-service "$php_fpm_service" restart
    mkdir -p /run/nginx
    nginx -t
    if rc-service nginx status >/dev/null 2>&1; then
        rc-service nginx reload || rc-service nginx restart
    else
        rc-service nginx start
    fi
    rc-service crond restart || true
}

require_root
require_alpine
apk_install
copy_project
configure_dirs
configure_doas
configure_php_fpm
configure_nginx
configure_mariadb
init_panel_db
configure_acme_cron
start_services

cat <<EOF

LightLNMP installed.
Panel URL: http://SERVER_IP:$PANEL_PORT/
Username: $ADMIN_USER
Password: $ADMIN_PASSWORD

Document root: $WEB_ROOT
Install dir: $INSTALL_DIR

EOF
