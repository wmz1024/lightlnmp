#!/bin/sh
set -eu

usage() {
    cat <<USAGE
Usage: sh update.sh [options]

Auto-detect the current system and run the matching LightLNMP updater.
Currently supported systems: Alpine Linux with OpenRC, Debian with systemd.

Common options:
  --from-repo             Fetch the latest source from GitHub before updating
  --install-dir PATH      Default: /opt/lightlnmp
  --web-root PATH         Default: /www/wwwroot
  --ssl-root PATH         Default: /etc/lightlnmp/ssl
  --panel-port PORT       Default: 8888
  --no-system-config      Only update panel/bin/config files; do not rewrite /etc configs
  --no-reload             Do not restart/reload PHP-FPM, Nginx, or crond
  -h, --help              Show this help
USAGE
}

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    usage
    exit 0
fi

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

if [ -f /etc/alpine-release ]; then
    if [ ! -f "$script_dir/update_alpine.sh" ]; then
        echo "Missing Alpine updater: $script_dir/update_alpine.sh" >&2
        exit 1
    fi
    sh "$script_dir/update_alpine.sh" "$@"
    exit $?
fi

if [ -f /etc/debian_version ]; then
    if [ ! -f "$script_dir/update_debian.sh" ]; then
        echo "Missing Debian updater: $script_dir/update_debian.sh" >&2
        exit 1
    fi
    sh "$script_dir/update_debian.sh" "$@"
    exit $?
fi

cat >&2 <<'MSG'
Unsupported system.
LightLNMP currently supports Alpine Linux with OpenRC and Debian with systemd.
MSG
exit 1
