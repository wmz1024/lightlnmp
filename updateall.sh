#!/bin/sh
set -eu

REPO_URL="${LIGHTLNMP_REPO_URL:-https://github.com/wmz1024/lightlnmp.git}"

usage() {
    cat <<USAGE
Usage: sh updateall.sh [update options]

Clone the latest LightLNMP source from GitHub, auto-detect the current system,
and update an existing installation. Currently supported system: Alpine Linux
with OpenRC.

Examples:
  sh updateall.sh
  sh updateall.sh --no-reload
  sh updateall.sh --panel-port 8888 --install-dir /opt/lightlnmp

Repository:
  $REPO_URL
USAGE
}

require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        echo "This updater must be run as root." >&2
        exit 1
    fi
}

detect_system() {
    if [ -f /etc/alpine-release ]; then
        if ! command -v rc-service >/dev/null 2>&1; then
            echo "Alpine was detected, but OpenRC was not found." >&2
            exit 1
        fi
        echo "alpine"
        return 0
    fi
    echo "unsupported"
}

ensure_git_alpine() {
    if command -v git >/dev/null 2>&1; then
        return 0
    fi
    apk update
    apk add --no-cache git ca-certificates
}

run_alpine_update() {
    ensure_git_alpine
    tmp_dir=$(mktemp -d "${TMPDIR:-/tmp}/lightlnmp-update.XXXXXX")
    trap 'rm -rf "$tmp_dir"' EXIT HUP INT TERM
    repo_dir="$tmp_dir/lightlnmp"

    git clone --depth 1 "$REPO_URL" "$repo_dir"
    sh "$repo_dir/update.sh" "$@"
}

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    usage
    exit 0
fi

require_root

case "$(detect_system)" in
    alpine) run_alpine_update "$@" ;;
    *)
        cat >&2 <<'MSG'
Unsupported system.
LightLNMP currently supports Alpine Linux with OpenRC only.
MSG
        exit 1
        ;;
esac
