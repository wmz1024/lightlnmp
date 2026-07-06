#!/bin/sh
set -eu

REPO_URL="${LIGHTLNMP_REPO_URL:-https://github.com/wmz1024/lightlnmp.git}"

usage() {
    cat <<USAGE
Usage: sh installall.sh [install_alpine.sh options]

Clone LightLNMP from GitHub, auto-detect the current system, and run the
matching installer. Currently supported system: Alpine Linux with OpenRC.

Examples:
  sh installall.sh
  sh installall.sh --with-mariadb
  sh installall.sh --admin-password 'change-this-password' --panel-port 8888

Repository:
  $REPO_URL
USAGE
}

require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        echo "This installer must be run as root." >&2
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

run_alpine_install() {
    ensure_git_alpine
    tmp_dir=$(mktemp -d "${TMPDIR:-/tmp}/lightlnmp-install.XXXXXX")
    trap 'rm -rf "$tmp_dir"' EXIT HUP INT TERM
    repo_dir="$tmp_dir/lightlnmp"

    git clone --depth 1 "$REPO_URL" "$repo_dir"
    sh "$repo_dir/install_alpine.sh" "$@"
}

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    usage
    exit 0
fi

require_root

case "$(detect_system)" in
    alpine) run_alpine_install "$@" ;;
    *)
        cat >&2 <<'MSG'
Unsupported system.
LightLNMP currently supports Alpine Linux with OpenRC only.
MSG
        exit 1
        ;;
esac
