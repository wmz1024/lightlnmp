#!/bin/sh
set -eu

INSTALL_DIR="/opt/lightlnmp"
WEB_ROOT="/www/wwwroot"
SSL_ROOT="/etc/lightlnmp/ssl"
PANEL_PORT="8888"
PANEL_USER="www-data"
APPLY_SYSTEM_CONFIG="1"
RELOAD_SERVICES="1"
FROM_REPO="0"
REPO_URL="${LIGHTLNMP_REPO_URL:-https://github.com/wmz1024/lightlnmp.git}"

usage() {
    cat <<USAGE
Usage: sh update_debian.sh [options]

Update an existing LightLNMP installation on Debian from this repository
checkout, or fetch the latest source from GitHub with --from-repo.

Options:
  --install-dir PATH       Default: /opt/lightlnmp
  --web-root PATH          Default: /www/wwwroot
  --ssl-root PATH          Default: /etc/lightlnmp/ssl
  --panel-port PORT        Default: 8888
  --from-repo              Fetch the latest source from GitHub before updating
  --no-system-config       Only update panel/bin/config files; do not rewrite /etc configs
  --no-reload              Do not restart/reload PHP-FPM, Nginx, or cron
  -h, --help               Show this help
USAGE
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --install-dir) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; INSTALL_DIR="$2"; shift ;;
        --web-root) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; WEB_ROOT="$2"; shift ;;
        --ssl-root) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; SSL_ROOT="$2"; shift ;;
        --panel-port) [ "$#" -ge 2 ] || { echo "Option $1 requires a value." >&2; exit 1; }; PANEL_PORT="$2"; shift ;;
        --from-repo) FROM_REPO="1" ;;
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

require_debian() {
    if [ ! -f /etc/debian_version ]; then
        echo "This updater currently supports Debian only." >&2
        exit 1
    fi
    if ! command -v systemctl >/dev/null 2>&1; then
        echo "systemd was not found. This updater expects Debian with systemd." >&2
        exit 1
    fi
}

require_existing_install() {
    if [ ! -d "$INSTALL_DIR" ]; then
        echo "Install dir not found: $INSTALL_DIR" >&2
        echo "Run install_debian.sh first, or pass --install-dir." >&2
        exit 1
    fi
}

ensure_git() {
    if command -v git >/dev/null 2>&1; then
        return 0
    fi
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y --no-install-recommends git ca-certificates
}

update_from_repo() {
    ensure_git
    tmp_dir=$(mktemp -d "${TMPDIR:-/tmp}/lightlnmp-update.XXXXXX")
    trap 'rm -rf "$tmp_dir"' EXIT HUP INT TERM
    repo_dir="$tmp_dir/lightlnmp"

    git clone --depth 1 "$REPO_URL" "$repo_dir"

    set -- --install-dir "$INSTALL_DIR" --web-root "$WEB_ROOT" --ssl-root "$SSL_ROOT" --panel-port "$PANEL_PORT"
    if [ "$APPLY_SYSTEM_CONFIG" = "0" ]; then
        set -- "$@" --no-system-config
    fi
    if [ "$RELOAD_SERVICES" = "0" ]; then
        set -- "$@" --no-reload
    fi

    sh "$repo_dir/update_debian.sh" "$@"
}

source_dir() {
    CDPATH= cd -- "$(dirname -- "$0")" && pwd
}

require_source_tree() {
    src_dir="$1"
    for path in panel/app panel/public bin/llctl config/nginx/panel.conf.tpl config/php/lightlnmp.conf.tpl update_debian.sh; do
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
    for file in installall.sh install_alpine.sh install_debian.sh updateall.sh update.sh update_alpine.sh update_debian.sh; do
        if [ -f "$src_dir/$file" ]; then
            cp "$src_dir/$file" "$INSTALL_DIR/$file"
        fi
    done

    mkdir -p "$INSTALL_DIR/panel/storage/logs" "$WEB_ROOT" "$SSL_ROOT" /etc/lightlnmp/rewrite
    chmod 0755 "$INSTALL_DIR/bin/llctl"
    chmod 0755 "$INSTALL_DIR"/*.sh 2>/dev/null || true
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

install_runtime_packages() {
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y --no-install-recommends php-zip php-mysql php-sqlite3 php-mbstring php-curl php-xml opendoas
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
    fi
    render_template "$INSTALL_DIR/config/nginx/panel.conf.tpl" /etc/nginx/http.d/lightlnmp-panel.conf
    if [ -e /etc/nginx/sites-enabled/default ]; then
        rm -f /etc/nginx/sites-enabled/default
    fi
}

configure_acme_cron() {
    cat > /etc/cron.daily/lightlnmp-acme-renew <<EOF
#!/bin/sh
$INSTALL_DIR/bin/llctl ssl renew-all >> $INSTALL_DIR/panel/storage/logs/acme-renew.log 2>&1
EOF
    chmod 0755 /etc/cron.daily/lightlnmp-acme-renew
    systemctl enable cron >/dev/null 2>&1 || true
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
INSERT OR REPLACE INTO settings(key, value) VALUES('panel_port', '$PANEL_PORT');
INSERT OR REPLACE INTO settings(key, value) VALUES('php_fpm_service', '$php_fpm_service_sql');
SQL
}

reload_services() {
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
require_existing_install
install_runtime_packages

if [ "$FROM_REPO" = "1" ]; then
    update_from_repo
    exit $?
fi

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
