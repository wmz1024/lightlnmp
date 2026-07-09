#!/bin/sh
set -eu

APP_NAME="lightlnmp"
INSTALL_DIR="/opt/lightlnmp"
WEB_ROOT="/www/wwwroot"
SSL_ROOT="/etc/lightlnmp/ssl"
PANEL_PORT="8888"
PANEL_USER="www-data"
WITH_MARIADB="0"
ADMIN_USER="admin"
ADMIN_PASSWORD=""

usage() {
    cat <<USAGE
Usage: sh install_debian.sh [options]

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

require_debian() {
    if [ ! -f /etc/debian_version ]; then
        echo "This installer currently supports Debian only." >&2
        exit 1
    fi
    if ! command -v systemctl >/dev/null 2>&1; then
        echo "systemd was not found. This installer expects Debian with systemd." >&2
        exit 1
    fi
}

apt_install() {
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y --no-install-recommends \
        nginx opendoas sqlite3 curl ca-certificates openssl git cron \
        php-cli php-fpm php-sqlite3 php-mbstring php-curl php-zip php-xml php-mysql

    if [ "$WITH_MARIADB" = "1" ]; then
        apt-get install -y --no-install-recommends mariadb-server mariadb-client
    fi

    if ! apt-get install -y --no-install-recommends acme.sh; then
        echo "Notice: failed to install acme.sh from apt repositories; SSL issuance will be unavailable until acme.sh is installed." >&2
    fi
}

detect_php_version() {
    php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || true
}

detect_php_fpm_service() {
    php_version=$(detect_php_version)
    if [ -n "$php_version" ] && systemctl list-unit-files "php$php_version-fpm.service" --no-legend 2>/dev/null | grep -q "^php$php_version-fpm.service"; then
        echo "php$php_version-fpm"
        return 0
    fi
    for svc in php8.4-fpm php8.3-fpm php8.2-fpm php8.1-fpm php8.0-fpm php-fpm; do
        if systemctl list-unit-files "$svc.service" --no-legend 2>/dev/null | grep -q "^$svc.service"; then
            echo "$svc"
            return 0
        fi
    done
    echo "php-fpm"
}

detect_php_fpm_pool_dir() {
    php_version=$(detect_php_version)
    if [ -n "$php_version" ] && [ -d "/etc/php/$php_version/fpm/pool.d" ]; then
        echo "/etc/php/$php_version/fpm/pool.d"
        return 0
    fi
    for dir in /etc/php/*/fpm/pool.d; do
        if [ -d "$dir" ]; then
            echo "$dir"
            return 0
        fi
    done
    return 1
}

detect_php_fpm_conf_dir() {
    php_version=$(detect_php_version)
    if [ -n "$php_version" ] && [ -d "/etc/php/$php_version/fpm/conf.d" ]; then
        echo "/etc/php/$php_version/fpm/conf.d"
        return 0
    fi
    for dir in /etc/php/*/fpm/conf.d; do
        if [ -d "$dir" ]; then
            echo "$dir"
            return 0
        fi
    done
    return 1
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
    for file in installall.sh install_alpine.sh install_debian.sh updateall.sh update.sh update_alpine.sh update_debian.sh; do
        if [ -f "$src_dir/$file" ]; then
            cp "$src_dir/$file" "$INSTALL_DIR/$file"
        fi
    done
    chmod 0755 "$INSTALL_DIR/bin/llctl"
    chmod 0755 "$INSTALL_DIR"/*.sh 2>/dev/null || true
}

configure_dirs() {
    mkdir -p "$WEB_ROOT" "$SSL_ROOT" "$INSTALL_DIR/panel/storage/logs" /etc/lightlnmp/rewrite
    chown -R "$PANEL_USER:$PANEL_USER" "$WEB_ROOT" "$INSTALL_DIR/panel/storage"
    chmod 0750 "$INSTALL_DIR/panel/storage"
    chmod 0750 "$SSL_ROOT"
}

configure_doas() {
    touch /etc/doas.conf
    tmp=$(mktemp)
    sed '/^# LightLNMP begin$/,/^# LightLNMP end$/d' /etc/doas.conf > "$tmp"
    cat >> "$tmp" <<EOF
# LightLNMP begin
permit nopass $PANEL_USER as root cmd $INSTALL_DIR/bin/llctl
# LightLNMP end
EOF
    cat "$tmp" > /etc/doas.conf
    rm -f "$tmp"
    chmod 0600 /etc/doas.conf
}

configure_php_fpm() {
    pool_dir=$(detect_php_fpm_pool_dir || true)
    if [ -z "$pool_dir" ]; then
        echo "Unable to detect PHP-FPM pool directory." >&2
        exit 1
    fi
    render_template "$INSTALL_DIR/config/php/lightlnmp.conf.tpl" "$pool_dir/lightlnmp.conf"

    conf_dir=$(detect_php_fpm_conf_dir || true)
    if [ -n "$conf_dir" ]; then
        render_template "$INSTALL_DIR/config/php/99-lightlnmp.ini.tpl" "$conf_dir/99-lightlnmp.ini"
    fi
}

configure_nginx() {
    mkdir -p /etc/nginx/http.d /run/nginx /etc/lightlnmp/rewrite
    if [ -f "$INSTALL_DIR/config/nginx/debian-nginx.conf.tpl" ]; then
        cp "$INSTALL_DIR/config/nginx/debian-nginx.conf.tpl" /etc/nginx/nginx.conf
    else
        cp "$INSTALL_DIR/config/nginx/nginx.conf.tpl" /etc/nginx/nginx.conf
    fi
    render_template "$INSTALL_DIR/config/nginx/panel.conf.tpl" /etc/nginx/http.d/lightlnmp-panel.conf
    if [ -e /etc/nginx/sites-enabled/default ]; then
        rm -f /etc/nginx/sites-enabled/default
    fi
}

configure_mariadb() {
    if [ "$WITH_MARIADB" != "1" ]; then
        return 0
    fi
    systemctl enable mariadb >/dev/null 2>&1 || true
    systemctl start mariadb || true
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
  rewrite_rule TEXT NOT NULL DEFAULT 'default',
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
CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL,
  ip TEXT NOT NULL,
  failed_count INTEGER NOT NULL DEFAULT 0,
  locked_until INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(username, ip)
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
    cat > /etc/cron.daily/lightlnmp-acme-renew <<EOF
#!/bin/sh
$INSTALL_DIR/bin/llctl ssl renew-all >> $INSTALL_DIR/panel/storage/logs/acme-renew.log 2>&1
EOF
    chmod 0755 /etc/cron.daily/lightlnmp-acme-renew
    systemctl enable cron >/dev/null 2>&1 || true
}

start_services() {
    php_fpm_service=$(detect_php_fpm_service)
    systemctl enable nginx >/dev/null 2>&1 || true
    systemctl enable "$php_fpm_service" >/dev/null 2>&1 || true
    systemctl restart "$php_fpm_service"
    nginx -t
    if systemctl is-active --quiet nginx; then
        systemctl reload nginx || systemctl restart nginx
    else
        systemctl start nginx
    fi
    systemctl restart cron || true
}

require_root
require_debian
apt_install
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
