#!/usr/bin/env bash

set -euo pipefail

phar_source="${1:-builds/blacklabs}"
release_dir="${2:-release-assets}"
php_version="${PHPACKER_PHP_VERSION:-8.4}"
phpacker_bin="${PHPACKER_BIN:-}"

if [[ ! -f "$phar_source" ]]; then
    echo "Expected a built phar at $phar_source" >&2
    exit 1
fi

if [[ -z "$phpacker_bin" ]]; then
    if [[ -x "./vendor/bin/phpacker" ]]; then
        phpacker_bin="./vendor/bin/phpacker"
    elif command -v phpacker >/dev/null 2>&1; then
        phpacker_bin="$(command -v phpacker)"
    else
        echo "phpacker must be installed as a project dependency or available on PATH to build packed binaries." >&2
        exit 1
    fi
fi

rm -rf "$release_dir"
mkdir -p "$release_dir"

cp "$phar_source" "$release_dir/blacklabs"
cp "$phar_source" "$release_dir/blacklabs.phar"

raw_dir="$(mktemp -d "${TMPDIR:-/tmp}/blacklabs-phpacker-XXXXXX")"
trap 'rm -rf "$raw_dir"' EXIT

"$phpacker_bin" build all --src="$release_dir/blacklabs.phar" --php="$php_version" --dest="$raw_dir"

while IFS= read -r -d '' file; do
    lower_path="$(printf '%s' "$file" | tr '[:upper:]' '[:lower:]')"
    destination=''

    case "$lower_path" in
        *windows*x64*.exe)
            destination="$release_dir/blacklabs-windows-x64.exe"
            ;;
        *mac*arm64*|*darwin*arm64*|*macos*arm64*)
            destination="$release_dir/blacklabs-macos-arm64"
            ;;
        *mac*x64*|*mac*amd64*|*darwin*x64*|*darwin*amd64*|*macos*x64*)
            destination="$release_dir/blacklabs-macos-x64"
            ;;
        *linux*arm64*|*linux*aarch64*)
            destination="$release_dir/blacklabs-linux-arm64"
            ;;
        *linux*x64*|*linux*amd64*)
            destination="$release_dir/blacklabs-linux-x64"
            ;;
    esac

    if [[ -n "$destination" ]]; then
        cp "$file" "$destination"
    fi
done < <(find "$raw_dir" -type f -print0)

rm -f "$release_dir/blacklabs.phar"

if [[ ! -f "$release_dir/blacklabs" ]]; then
    echo "Failed to prepare the PHAR release asset." >&2
    exit 1
fi

find "$release_dir" -maxdepth 1 -type f | sort
