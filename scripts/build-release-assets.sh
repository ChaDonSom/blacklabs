#!/usr/bin/env bash

set -euo pipefail

phar_source="${1:-builds/blacklabs}"
release_dir="${2:-release-assets}"
php_version="${PHPACKER_PHP_VERSION:-8.4}"
packager_bin="${PHPACKER_BIN:-}"
asset_name_script="./scripts/release-asset-name.php"

if [[ ! -f "$phar_source" ]]; then
    echo "Expected a built phar at $phar_source" >&2
    exit 1
fi

if [[ ! -f "$asset_name_script" ]]; then
    echo "Expected an asset naming helper at $asset_name_script" >&2
    exit 1
fi

if [[ -z "$packager_bin" ]]; then
    if [[ -x "./vendor/bin/phpacker" ]]; then
        packager_bin="./vendor/bin/phpacker"
    elif command -v phpacker >/dev/null 2>&1; then
        packager_bin="$(command -v phpacker)"
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

"$packager_bin" build all --src="$release_dir/blacklabs.phar" --php="$php_version" --dest="$raw_dir"

while IFS= read -r -d '' file; do
    destination_name="$(php "$asset_name_script" "$file")"

    if [[ -n "$destination_name" ]]; then
        cp "$file" "$release_dir/$destination_name"
    fi
done < <(find "$raw_dir" -type f -print0)

rm -f "$release_dir/blacklabs.phar"

if [[ ! -f "$release_dir/blacklabs" ]]; then
    echo "Failed to prepare the PHAR release asset." >&2
    exit 1
fi

find "$release_dir" -maxdepth 1 -type f | sort
