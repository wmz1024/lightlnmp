#!/bin/sh
set -eu

INSTALL_DIR="/opt/lightlnmp"
WEB_ROOT="/www/wwwroot"
SSL_ROOT="/etc/lightlnmp/ssl"
PANEL_PORT="8888"
PANEL_USER="nginx"
APPLY_SYSTEM_CONFIG="1"
RELOAD_SERVICES="1"

usage() {
    cat <<USAGE
Usage: sh update_alpine.sh [options]

Update an existing LightLNMP installation from this repository checkout.

Options:
  --install-dir PATH       Default: /opt/lightlnmp
  --web-root PATH          Default: /www/wwwroot
  --ssl-root PATH          Default: /etc/lightlnmp/ssl
  --panel-port PORT        Default: 8888
  --no-system-config       Only update panel/bin/config files; do not rewrite /etc configs
  --no-reload              Do not restart/reload PHP-FPM, Nginx, or crond
  -h, --help               Show this help
USAGE
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --install-dir) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; INSTALL_DIR="$2"; shift ;;
        --web-root) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; WEB_ROOT="$2"; shift ;;
        --ssl-root) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; SSL_ROOT="$2"; shift ;;
        --panel-port) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; PANEL_PORT="$2"; shift ;;
        --no-system-config) APPLY_SYSTEM_CONFIG="0" ;;
        --no-reload) RELOAD_SERVICES="0" ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
    esac
    shift
done

case "$PANEL_PORT" in
    *[!0-9]*|'') echo "Invalid panel port." >&2; exit 1 ;;
esac

if [ "$PANEL_PORT" -lt 1 ] || [ "$PANEL_PORT" -gt 65535 ]; then
    echo "Panel port must be between 1 and 65535." >&2
    exit 1
fi

require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        echo "This updater must be run as root." >&2
        exit 1
    fi
}

require_alpine() {
    if [ ! -f /etc/alpine-release ]; then
        echo "This updater currently supports Alpine Linux only." >&2
        exit 1
    fi
    if ! command -v rc-service >/dev/null 2>&1; then
        echo "OpenRC was not found. This updater expects Alpine with OpenRC." >&2
        exit 1
    fi
}

require_existing_install() {
    if [ ! -d "$INSTALL_DIR" ]; then
        echo "Install dir not found: $INSTALL_DIR" >&2
        echo "Run install_alpine.sh first, or pass --install-dir." >&2
        exit 1
    fi
}

source_dir() {
    CDPATH= cd -- "$(dirname -- "$0")" && pwd
}

require_source_tree() {
    src_dir="$1"
    for path in panel/app panel/public bin/llctl config/nginx/nginx.conf.tpl config/php/lightlnmp.conf.tpl; do
        if [ ! -e "$src_dir/$path" ]; then
            echo "Missing source file or directory: $src_dir/$path" >&2
            exit 1
        fi
    done
}

backup_install() {
    timestamp=$(date +%Y%m%d%H%M%S)
    backup_root="$(dirname "$INSTALL_DIR")/$(basename "$INSTALL_DIR").backups"
    backup_file="$backup_root/update-$timestamp.tar"
    mkdir -p "$backup_root"
    tar -cf "$backup_file" -C "$(dirname "$INSTALL_DIR")" "$(basename "$INSTALL_DIR")"
    echo "Backup created: $backup_file"
}

copy_project_files() {
    src_dir="$1"

    mkdir -p "$INSTALL_DIR/panel"
    rm -rf "$INSTALL_DIR/panel/app" "$INSTALL_DIR/panel/public" "$INSTALL_DIR/bin" "$INSTALL_DIR/config"
    cp -R "$src_dir/panel/app" "$INSTALL_DIR/panel/"
    cp -R "$src_dir/panel/public" "$INSTALL_DIR/panel/"
    cp -R "$src_dir/bin" "$INSTALL_DIR/"
    cp -R "$src_dir/config" "$INSTALL_DIR/"

    mkdir -p "$INSTALL_DIR/panel/storage/logs" "$WEB_ROOT" "$SSL_ROOT"
    chmod 0755 "$INSTALL_DIR/bin/llctl"
    chown -R "$PANEL_USER:$PANEL_USER" "$INSTALL_DIR/panel/storage" "$WEB_ROOT" 2>/dev/null || true
    chmod 0750 "$INSTALL_DIR/panel/storage" 2>/dev/null || true
    chmod 0750 "$SSL_ROOT" 2>/dev/null || true
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

sql_escape() {
    printf '%s' "$1" | sed "s/'/''/g"
}

detect_php_conf_dir() {
    php -r 'echo dirname(php_ini_loaded_file());' 2>/dev/null || true
}

detect_php_suffix() {
    php -r 'echo PHP_MAJOR_VERSION . PHP_MINOR_VERSION;' 2>/dev/null || true
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

configure_doas() {
    mkdir -p /etc/doas.d
    cat > /etc/doas.d/lightlnmp.conf <<EOF
permit nopass $PANEL_USER as root cmd $INSTALL_DIR/bin/llctl
EOF
    chmod 0600 /etc/doas.d/lightlnmp.conf
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

configure_nginx() {
    mkdir -p /etc/nginx/http.d /run/nginx
    stop_legacy_nginx
    cp "$INSTALL_DIR/config/nginx/nginx.conf.tpl" /etc/nginx/nginx.conf
    render_template "$INSTALL_DIR/config/nginx/panel.conf.tpl" /etc/nginx/http.d/lightlnmp-panel.conf
    if [ -f /etc/nginx/http.d/default.conf ]; then
        mv /etc/nginx/http.d/default.conf /etc/nginx/http.d/default.conf.disabled
    fi
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

configure_acme_cron() {
    mkdir -p /etc/periodic/daily
    cat > /etc/periodic/daily/lightlnmp-acme-renew <<EOF
#!/bin/sh
$INSTALL_DIR/bin/llctl ssl renew-all >> $INSTALL_DIR/panel/storage/logs/acme-renew.log 2>&1
EOF
    chmod 0755 /etc/periodic/daily/lightlnmp-acme-renew
    rc-update add crond default || true
}

update_panel_settings() {
    db="$INSTALL_DIR/panel/storage/panel.sqlite"
    if [ ! -f "$db" ] || ! command -v sqlite3 >/dev/null 2>&1; then
        return 0
    fi
    php_fpm_service=$(detect_php_fpm_service)
    install_dir_sql=$(sql_escape "$INSTALL_DIR")
    web_root_sql=$(sql_escape "$WEB_ROOT")
    ssl_root_sql=$(sql_escape "$SSL_ROOT")
    php_fpm_service_sql=$(sql_escape "$php_fpm_service")
    sqlite3 "$db" <<SQL
INSERT OR REPLACE INTO settings(key, value) VALUES('install_dir', '$install_dir_sql');
INSERT OR REPLACE INTO settings(key, value) VALUES('web_root', '$web_root_sql');
INSERT OR REPLACE INTO settings(key, value) VALUES('ssl_root', '$ssl_root_sql');
INSERT OR REPLACE INTO settings(key, value) VALUES('php_fpm_service', '$php_fpm_service_sql');
SQL
}

reload_services() {
    php_fpm_service=$(detect_php_fpm_service)
    rc-update add nginx default || true
    rc-update add "$php_fpm_service" default || true
    rc-service "$php_fpm_service" restart
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
require_existing_install
SRC_DIR=$(source_dir)
require_source_tree "$SRC_DIR"

backup_install
copy_project_files "$SRC_DIR"
update_panel_settings

if [ "$APPLY_SYSTEM_CONFIG" = "1" ]; then
    configure_doas
    configure_php_fpm
    configure_nginx
    configure_acme_cron
fi

if [ "$RELOAD_SERVICES" = "1" ]; then
    reload_services
fi

cat <<EOF

LightLNMP update completed.
Install dir: $INSTALL_DIR
Panel URL: http://SERVER_IP:$PANEL_PORT/

EOF
