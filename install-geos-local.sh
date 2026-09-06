#!/usr/bin/env bash

set -Eeuo pipefail

geos_commit="889e2b1d9aa6f82995db4dab6891f48c3bacfb59"
archive_sha256="c20ab4c6c0b2c27f8fee06191d623c76df696907267e78aeee12c7bbbe66b7af"
php_minor="8.5"
managed_marker="; Managed by Rodnik install-geos-local.sh."

fail() {
    echo "Error: $*" >&2
    exit 1
}

usage() {
    cat <<'USAGE'
Usage: install-geos-local.sh [--plan|--execute]

Install the pinned PHP GEOS extension for this project's Herd PHP 8.5 on macOS.
Without --execute, print the plan without changing anything.

  --plan       Print the installation plan (default).
  --execute    Install Homebrew dependencies, build GEOS, enable it, restart Herd.
  -h, --help   Show this help.

Run as your normal macOS user, with Herd, Homebrew and Xcode Command Line Tools
already installed. This script never switches the site's PHP version.
USAGE
}

show_plan() {
    usage
    cat <<PLAN

Plan:
  1. Resolve this project's Herd PHP; require PHP ${php_minor}, NTS, API 20250925.
  2. Check CLI/FPM configuration and refuse unmanaged GEOS extensions.
  3. Install Homebrew geos, php@${php_minor}, autoconf and pkgconf dependencies.
  4. Download php-geos commit ${geos_commit} and verify SHA-256.
  5. Build with matching Homebrew PHP tools and test polygon predicates in Herd.
  6. Save a separate module and 99-rodnik-geos.ini in Herd's PHP 85 config directory.
  7. Verify the enabled extension, validate FPM, and restart Herd.

No changes made. Use --execute to install. Existing queue workers need a restart.
PLAN
}

verify_checksum() {
    local actual_checksum
    actual_checksum="$(shasum -a 256 "$1" | awk '{print $1}')"
    [[ "$actual_checksum" == "$archive_sha256" ]] || fail "php-geos archive checksum mismatch."
}

validate_php_binary() {
    local binary="$1" info
    [[ -x "$binary" ]] || fail "PHP binary is missing: $binary"
    info="$("$binary" -n -i)"
    grep -Eq '^PHP Version => 8\.5\.' <<< "$info" || fail "Expected PHP 8.5: $binary"
    grep -Eq '^PHP Extension Build => API20250925,NTS$' <<< "$info" || fail "Incompatible PHP module ABI: $binary"
    grep -Eq '^Debug Build => no$' <<< "$info" || fail "A non-debug PHP build is required: $binary"
    grep -Eq '^Thread Safety => disabled$' <<< "$info" || fail "A non-thread-safe PHP build is required: $binary"
}

validate_runtime() {
    validate_php_binary "$php_bin"
    validate_php_binary "$fpm_bin"
}

read_fpm_modules() {
    local modules
    # Read to EOF: an early-exiting grep can make FPM fail under pipefail.
    if ! modules="$("$fpm_bin" -m 2>&1)"; then
        fail "PHP-FPM could not list its modules: $modules"
    fi
    if [[ "$modules" == *Warning* || "$modules" == *'Unable to load'* ]]; then
        fail "PHP-FPM reported an extension loading problem: $modules"
    fi
    printf '%s\n' "$modules"
}

verify_fpm_extension() {
    local modules
    modules="$(read_fpm_modules)"
    grep -Fxi geos <<< "$modules" >/dev/null || fail "GEOS did not load in Herd PHP-FPM."
}

check_existing_configuration() {
    local ini_file
    if [[ -e "$managed_ini" || -L "$managed_ini" ]]; then
        [[ ! -L "$managed_ini" && -f "$managed_ini" ]] || fail "Managed INI must be a regular file: $managed_ini"
        [[ "$(head -n 1 "$managed_ini")" == "$managed_marker" ]] || fail "Refusing to overwrite unmanaged INI: $managed_ini"
    fi

    for ini_file in "$ini_dir"/*.ini; do
        [[ -f "$ini_file" && "$ini_file" != "$managed_ini" ]] || continue
        if grep -Eiq '^[[:space:]]*(zend_)?extension[[:space:]]*=.*geos' "$ini_file"; then
            fail "GEOS is already configured outside this installer: $ini_file"
        fi
    done

    if [[ ! -f "$managed_ini" ]] && "$php_bin" -r 'exit(extension_loaded("geos") ? 0 : 1);'; then
        fail "An unmanaged GEOS extension is already loaded. Remove its configuration first."
    fi
}

smoke_test() {
    local module="${1:-}"
    local php_options=(-d display_errors=stderr)
    if [[ -n "$module" ]]; then
        php_options=(-n -d display_errors=stderr -d "extension=$module")
    fi

    "$php_bin" "${php_options[@]}" <<'PHP'
<?php
if (! extension_loaded('geos') || ! class_exists('GEOSWKTReader')) {
    fwrite(STDERR, "GEOS did not load.\n");
    exit(1);
}
$reader = new GEOSWKTReader();
$polygon = $reader->read('POLYGON((0 0,10 0,10 10,0 10,0 0),(3 3,7 3,7 7,3 7,3 3))');
foreach ([
    ['POINT(2 2)', true, true],
    ['POINT(12 2)', false, false],
    ['POINT(0 5)', false, true],
    ['POINT(5 5)', false, false],
    ['POINT(3 5)', false, true],
] as [$wkt, $contains, $covers]) {
    $point = $reader->read($wkt);
    if ($polygon->contains($point) !== $contains || $polygon->covers($point) !== $covers) {
        fwrite(STDERR, "Unexpected GEOS predicate result for {$wkt}.\n");
        exit(1);
    }
}
$multi = $reader->read('MULTIPOLYGON(((0 0,2 0,2 2,0 2,0 0)),((10 10,12 10,12 12,10 12,10 10)))');
if (! $multi->contains($reader->read('POINT(11 11)')) || $multi->covers($reader->read('POINT(5 5)'))) {
    fwrite(STDERR, "Unexpected GEOS MultiPolygon result.\n");
    exit(1);
}
printf("GEOS smoke test passed: PHP %s, GEOS %s\n", PHP_VERSION, GEOSVersion());
PHP
}

write_managed_ini() {
    local destination="$1" module="$2" temporary_ini
    [[ "$module" != *'"'* && "$module" != *$'\n'* ]] || fail "Unsupported extension path."
    if [[ -e "$destination" || -L "$destination" ]]; then
        [[ ! -L "$destination" && -f "$destination" ]] || fail "Managed INI must be a regular file: $destination"
        [[ "$(head -n 1 "$destination")" == "$managed_marker" ]] || fail "Refusing to overwrite unmanaged INI: $destination"
    fi
    temporary_ini="$(mktemp "${destination}.XXXXXX")"
    printf '%s\nextension="%s"\n' "$managed_marker" "$module" > "$temporary_ini"
    chmod 0644 "$temporary_ini"
    if [[ -f "$destination" ]] && cmp -s "$temporary_ini" "$destination"; then
        rm -f "$temporary_ini"
    else
        mv -f "$temporary_ini" "$destination"
    fi
}

activate_extension() {
    write_managed_ini "$managed_ini" "$1"
    smoke_test
}

cleanup() {
    local status=$?
    trap - EXIT
    if [[ "$status" -ne 0 && "${configuration_changed:-false}" == true ]]; then
        if [[ "${had_managed_ini:-false}" == true ]]; then
            cp -p "$work_dir/previous.ini" "$managed_ini"
        else
            rm -f "$managed_ini"
        fi
        echo "Restored the previous GEOS INI. Dependencies and built modules were retained." >&2
        if [[ "${restart_attempted:-false}" == true ]]; then
            echo "Herd may have loaded the new module; restart it after reviewing the restored configuration." >&2
        fi
    fi
    if [[ -n "${work_dir:-}" && -d "$work_dir" ]]; then
        rm -rf "$work_dir"
    fi
    exit "$status"
}

main() {
    local mode="${1:---plan}" project_dir scan_dir loaded_ini brew_php_prefix geos_prefix
    local php_config phpize source_dir module_dir module_path build_jobs fpm_config
    [[ "$#" -le 1 ]] || { usage >&2; exit 2; }
    case "$mode" in
        --plan) show_plan; return ;;
        -h|--help) usage; return ;;
        --execute) ;;
        *) echo "Unknown argument: $mode" >&2; usage >&2; exit 2 ;;
    esac

    [[ "$(uname -s)" == Darwin ]] || fail "This installer requires macOS. Use install-geos-server.sh on Ubuntu."
    [[ "$(id -u)" -ne 0 ]] || fail "Run as your macOS user, not root or sudo."
    for command_name in herd brew xcode-select curl shasum make; do
        command -v "$command_name" >/dev/null 2>&1 || fail "Required command is missing: $command_name"
    done
    xcode-select -p >/dev/null 2>&1 || fail "Install Xcode Command Line Tools first: xcode-select --install"

    project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    cd "$project_dir"
    php_bin="$(herd which-php)"
    [[ "$php_bin" == */Herd/bin/php85 ]] || fail "This project must use Herd PHP 8.5; resolved: $php_bin"
    fpm_bin="${php_bin}-fpm"
    validate_runtime
    ini_dir="$(cd "$(dirname "$php_bin")/../config/php/85" && pwd)"
    fpm_config="$(dirname "$php_bin")/../config/fpm/8.5-fpm.conf"
    [[ -f "$fpm_config" ]] || fail "Herd FPM configuration is missing: $fpm_config"
    managed_ini="$ini_dir/99-rodnik-geos.ini"
    for binary in "$php_bin" "$fpm_bin"; do
        scan_dir="$("$binary" -i | sed -n 's/^Scan this dir for additional .ini files => //p')"
        [[ "${scan_dir%/}" == "$ini_dir" ]] || fail "Unexpected PHP INI scan directory for $binary: $scan_dir"
        loaded_ini="$("$binary" -i | sed -n 's/^Loaded Configuration File => //p')"
        [[ "$loaded_ini" == '(none)' || "$loaded_ini" == "$ini_dir/php.ini" ]] || fail "Unexpected main PHP INI: $loaded_ini"
    done
    check_existing_configuration
    "$fpm_bin" -t -y "$fpm_config"

    export HOMEBREW_NO_AUTO_UPDATE=1 HOMEBREW_NO_ANALYTICS=1
    brew install "php@${php_minor}" geos autoconf pkgconf
    brew_php_prefix="$(brew --prefix "php@${php_minor}")"
    geos_prefix="$(brew --prefix geos)"
    validate_php_binary "$brew_php_prefix/bin/php"
    php_config="$brew_php_prefix/bin/php-config"
    phpize="$brew_php_prefix/bin/phpize"
    [[ -x "$php_config" && -x "$phpize" && -x "$geos_prefix/bin/geos-config" ]] || fail "PHP/GEOS build tools are missing."
    [[ "$("$php_config" --version)" == 8.5.* ]] || fail "Homebrew php-config must target PHP 8.5."
    "$phpize" --version | grep -E 'Zend Module Api No:[[:space:]]+20250925$' >/dev/null || fail "Homebrew phpize has an incompatible ABI."

    work_dir="$(mktemp -d "${TMPDIR:-/tmp}/rodnik-geos.XXXXXX")"
    trap cleanup EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM
    source_dir="$work_dir/source"
    mkdir "$source_dir"
    curl --fail --silent --show-error --location --proto '=https' --proto-redir '=https' --tlsv1.2 \
        --connect-timeout 20 --max-time 180 --retry 3 \
        "https://codeload.github.com/libgeos/php-geos/tar.gz/$geos_commit" -o "$work_dir/php-geos.tar.gz"
    verify_checksum "$work_dir/php-geos.tar.gz"
    tar -xzf "$work_dir/php-geos.tar.gz" -C "$source_dir" --strip-components=1
    build_jobs="$(sysctl -n hw.ncpu)"
    if [[ "$build_jobs" -gt 4 ]]; then
        build_jobs=4
    fi
    (
        cd "$source_dir"
        "$phpize"
        ./configure --enable-geos --with-php-config="$php_config" --with-geos-config="$geos_prefix/bin/geos-config"
        make -j "$build_jobs"
    )
    smoke_test "$source_dir/modules/geos.so"

    mkdir -p "$ini_dir/rodnik-geos"
    module_dir="$(mktemp -d "$ini_dir/rodnik-geos/${geos_commit}.XXXXXX")"
    chmod 0755 "$module_dir"
    module_path="$module_dir/geos.so"
    install -m 0755 "$source_dir/modules/geos.so" "$module_path"
    smoke_test "$module_path"
    if [[ -f "$managed_ini" ]]; then
        cp -p "$managed_ini" "$work_dir/previous.ini"
        had_managed_ini=true
    fi
    configuration_changed=true
    activate_extension "$module_path"
    "$fpm_bin" -t -y "$fpm_config"
    verify_fpm_extension
    restart_attempted=true
    herd restart
    smoke_test
    configuration_changed=false
    echo "Installed php-geos $geos_commit with GEOS $("$geos_prefix/bin/geos-config" --version)."
    echo "Herd CLI/FPM extension: $module_path"
    echo "INI: $managed_ini"
    echo "Restart this project's existing queue workers so they load the new extension."
}

if [[ "${BASH_SOURCE[0]:-$0}" == "$0" ]]; then
    main "$@"
fi
