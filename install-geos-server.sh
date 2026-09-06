#!/usr/bin/env bash

set -Eeuo pipefail

source_commit="889e2b1d9aa6f82995db4dab6891f48c3bacfb59"
archive_sha256="c20ab4c6c0b2c27f8fee06191d623c76df696907267e78aeee12c7bbbe66b7af"
archive_url="https://codeload.github.com/libgeos/php-geos/tar.gz/$source_commit"
php_bin="/usr/bin/php8.5"
fpm_bin="/usr/sbin/php-fpm8.5"
phpize_bin="/usr/bin/phpize8.5"
php_config_bin="/usr/bin/php-config8.5"
php_root="/etc/php/8.5"
expected_php_api="20250925"
extension_dir="/usr/lib/php/$expected_php_api"
ini_file="$php_root/mods-available/rodnik-geos.ini"
existing_module=""
module_path=""
work_dir=""
configuration_snapshot_taken=false
activation_started=false
had_ini=false
had_cli_link=false
had_fpm_link=false
fpm_reload_attempted=false

fail() {
    printf 'GEOS: %s\n' "$*" >&2
    exit 1
}

usage() {
    cat <<'TEXT'
Usage: install-geos-server.sh [--plan | --execute | --help]

No arguments or --plan: print the installation plan without changing anything.
--execute: install for existing Ubuntu 24.04 PHP 8.5 CLI/FPM; requires root.

Forge Recipe: run as root and prepend `set -- --execute` to the pasted script.
SSH: ssh USER@HOST 'sudo bash -s -- --execute' < install-geos-server.sh
TEXT
}

print_plan() {
    cat <<TEXT
Plan only; nothing will be installed or changed.
Target: Ubuntu 24.04, existing PHP 8.5 NTS CLI and FPM (API $expected_php_api).
Source: $archive_url
SHA256: $archive_sha256
1. Reject incompatible runtimes and unmanaged or duplicate GEOS configuration.
2. Reuse an existing managed module only if its geometry smoke tests pass.
3. Otherwise apt-get update and install libgeos-dev, php8.5-dev,
   build-essential, autoconf, pkg-config, curl and ca-certificates.
   Existing apt repositories are used; package dependencies may upgrade PHP.
4. Verify PHP build tools, download and verify the pinned archive, then compile
   with phpize8.5, php-config8.5 and /usr/bin/geos-config in a temporary directory.
5. Test contains/covers for inside, outside, boundaries, holes and MultiPolygon.
6. Install an immutable module under $extension_dir/rodnik-geos/$source_commit/.
   Manage $ini_file and only CLI/FPM 20-rodnik-geos.ini links.
7. Verify loaded CLI/FPM modules, test FPM configuration and reload active FPM.
   Restart existing queue workers and other PHP daemons manually in Forge.
Run with --execute as root to perform this plan.
TEXT
}

ini_contents() {
    printf '%s\n' \
        '; Managed by install-geos-server.sh' \
        "; Source: $source_commit SHA256: $archive_sha256" \
        "extension=\"$1\""
}

validate_runtime() {
    local cli_info fpm_info runtime_info
    [[ -x "$php_bin" && -x "$fpm_bin" ]] || fail 'Install PHP 8.5 CLI and FPM through Forge first.'
    [[ "$("$php_bin" -n -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION." ".(PHP_ZTS ? "ZTS" : "NTS");')" == '8.5 NTS' ]] \
        || fail 'PHP CLI must be PHP 8.5 NTS.'
    cli_info="$("$php_bin" -n -i)"
    fpm_info="$("$fpm_bin" -n -i)"
    [[ "$fpm_info" == *'PHP Version => 8.5.'* ]] || fail 'PHP-FPM must be PHP 8.5.'
    for runtime_info in "$cli_info" "$fpm_info"; do
        [[ "$(printf '%s\n' "$runtime_info" | sed -n 's/^PHP API => //p')" == "$expected_php_api" ]] \
            || fail "PHP CLI/FPM API must be $expected_php_api."
        [[ "$(printf '%s\n' "$runtime_info" | sed -n 's/^PHP Extension Build => //p')" == "API$expected_php_api,NTS" ]] \
            || fail 'PHP CLI/FPM extension builds must match and be NTS.'
    done
    [[ "$("$php_bin" -n -r 'echo ini_get("extension_dir");')" == "$extension_dir" ]] \
        || fail "Unexpected extension directory; expected $extension_dir."
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
    grep -Fxi geos <<< "$modules" >/dev/null || fail 'GEOS is not loaded in FPM; FPM was not reloaded.'
}

check_existing_configuration() {
    local candidate_ini sapi link existing_path fpm_modules
    existing_module=""
    if [[ -e "$ini_file" || -L "$ini_file" ]]; then
        [[ -f "$ini_file" && ! -L "$ini_file" ]] || fail "Refusing to replace $ini_file."
        existing_path="$(sed -n 's/^extension="\([^"]*\)"$/\1/p' "$ini_file")"
        [[ "$existing_path" == "$extension_dir/rodnik-geos/$source_commit/"build.*/geos.so ]] \
            || fail "Unrecognized GEOS module in $ini_file; reconcile it manually first."
        cmp -s "$ini_file" <(ini_contents "$existing_path") \
            || fail "Unmanaged or changed $ini_file; reconcile it manually first."
        existing_module="$existing_path"
    fi

    for sapi in cli fpm; do
        link="$php_root/$sapi/conf.d/20-rodnik-geos.ini"
        if [[ -e "$link" || -L "$link" ]]; then
            [[ -L "$link" && "$(readlink -f "$link")" == "$ini_file" ]] \
                || fail "Refusing to replace unmanaged $link."
        fi
    done

    while IFS= read -r -d '' candidate_ini; do
        case "$candidate_ini" in
            "$ini_file"|"$php_root/cli/conf.d/20-rodnik-geos.ini"|"$php_root/fpm/conf.d/20-rodnik-geos.ini") continue ;;
        esac
        if awk '
            /^[[:space:]]*[;#]/ { next }
            /^[[:space:]]*(zend_)?extension[[:space:]]*=/ && tolower($0) ~ /geos/ { found = 1 }
            END { exit !found }
        ' "$candidate_ini"; then
            fail "Existing GEOS configuration: $candidate_ini. Disable or reconcile it manually first."
        fi
    done < <(find "$php_root" -name '*.ini' \( -type f -o -type l \) -print0)

    if [[ -z "$existing_module" ]]; then
        [[ "$("$php_bin" -r 'echo extension_loaded("geos") ? "yes" : "no";')" == no ]] \
            || fail 'An unmanaged GEOS extension is already loaded in CLI.'
        fpm_modules="$(read_fpm_modules)"
        if grep -Fxi geos <<< "$fpm_modules" >/dev/null; then
            fail 'An unmanaged GEOS extension is already loaded in FPM.'
        fi
    fi
}

preflight() {
    [[ "$(id -u)" == 0 ]] || fail '--execute requires root; use a root Forge Recipe or sudo bash.'
    [[ -r /etc/os-release ]] || fail 'Cannot identify the server operating system.'
    local ID VERSION_ID
    # shellcheck source=/dev/null
    . /etc/os-release
    [[ "$ID" == ubuntu && "$VERSION_ID" == 24.04 ]] || fail 'Only Ubuntu 24.04 is supported by this installer.'
    [[ -d "$php_root/cli/conf.d" && -d "$php_root/fpm/conf.d" && -d "$php_root/mods-available" ]] \
        || fail 'The PHP 8.5 CLI/FPM configuration directories must already exist.'
    command -v systemctl >/dev/null || fail 'systemctl is required to manage the existing FPM service.'
    validate_runtime
    check_existing_configuration
    "$fpm_bin" -t
}

validate_build_tools() {
    local config_options
    [[ -x "$phpize_bin" && -x "$php_config_bin" && -x /usr/bin/geos-config ]] \
        || fail 'Missing versioned PHP development tools or geos-config.'
    [[ "$("$php_config_bin" --version)" == 8.5.* ]] || fail 'php-config8.5 must target PHP 8.5.'
    [[ "$("$php_config_bin" --phpapi)" == "$expected_php_api" ]] || fail 'php-config API does not match CLI/FPM.'
    [[ "$("$php_config_bin" --extension-dir)" == "$extension_dir" ]] || fail 'php-config extension directory does not match CLI.'
    "$phpize_bin" --version | grep -E "PHP Api Version:[[:space:]]+$expected_php_api$" >/dev/null \
        || fail 'phpize API does not match CLI/FPM.'
    config_options="$("$php_config_bin" --configure-options)"
    [[ "$config_options" != *--enable-zts* && "$config_options" != *--enable-maintainer-zts* ]] \
        || fail 'The PHP development build must be NTS.'
}

verify_checksum() {
    local actual_checksum
    actual_checksum="$(sha256sum "$1" | cut -d ' ' -f 1)"
    [[ "$actual_checksum" == "$archive_sha256" ]] || fail 'Downloaded GEOS source checksum does not match the pinned archive.'
}

smoke_test() {
    local output
    local -a php_options=(-d display_errors=stderr)
    if [[ -n "${1:-}" ]]; then
        php_options=(-n -d "extension=$1" -d display_errors=stderr)
    fi
    # shellcheck disable=SC2016
    if ! output="$("$php_bin" "${php_options[@]}" -r '
        if (!extension_loaded("geos") || !class_exists("GEOSWKTReader")) {
            fwrite(STDERR, "GEOS is not loaded.\n");
            exit(1);
        }
        $reader = new GEOSWKTReader();
        $polygon = $reader->read("POLYGON ((0 0, 10 0, 10 10, 0 10, 0 0), (3 3, 3 7, 7 7, 7 3, 3 3))");
        $multiPolygon = $reader->read("MULTIPOLYGON (((20 20, 24 20, 24 24, 20 24, 20 20)), ((30 30, 34 30, 34 34, 30 34, 30 30)))");
        $cases = [
            ["inside", $polygon, "POINT (1 1)", true, true],
            ["outside", $polygon, "POINT (11 1)", false, false],
            ["outer boundary", $polygon, "POINT (0 1)", false, true],
            ["hole", $polygon, "POINT (5 5)", false, false],
            ["hole boundary", $polygon, "POINT (3 5)", false, true],
            ["multipolygon inside", $multiPolygon, "POINT (32 32)", true, true],
            ["multipolygon outside", $multiPolygon, "POINT (26 26)", false, false],
            ["multipolygon boundary", $multiPolygon, "POINT (30 32)", false, true],
        ];
        foreach ($cases as [$name, $geometry, $wkt, $contains, $covers]) {
            $point = $reader->read($wkt);
            if ($geometry->contains($point) !== $contains || $geometry->covers($point) !== $covers) {
                fwrite(STDERR, "GEOS predicate failed: ".$name."\n");
                exit(1);
            }
        }
        echo "GEOS smoke tests passed: ".phpversion("geos")." / ".GEOSVersion()."\n";
    ' 2>&1)"; then
        printf '%s\n' "$output" >&2
        return 1
    fi
    if [[ "$output" != 'GEOS smoke tests passed: '* || "$output" == *$'\n'* ]]; then
        printf 'Unexpected PHP output (including possible startup warnings):\n%s\n' "$output" >&2
        return 1
    fi
    printf '%s\n' "$output"
}

cleanup() {
    if [[ -n "$work_dir" && -d "$work_dir" ]]; then
        rm -rf -- "$work_dir"
    fi
}

snapshot_configuration() {
    [[ -n "$work_dir" && -d "$work_dir" ]] || fail 'A temporary directory is required for the configuration backup.'
    had_ini=false
    had_cli_link=false
    had_fpm_link=false
    if [[ -f "$ini_file" ]]; then
        cp -p "$ini_file" "$work_dir/previous-rodnik-geos.ini"
        had_ini=true
    fi
    if [[ -L "$php_root/cli/conf.d/20-rodnik-geos.ini" ]]; then
        had_cli_link=true
    fi
    if [[ -L "$php_root/fpm/conf.d/20-rodnik-geos.ini" ]]; then
        had_fpm_link=true
    fi
    configuration_snapshot_taken=true
}

rollback_configuration() {
    local restore_file
    if [[ "$had_ini" == true ]]; then
        restore_file="$(mktemp "$php_root/mods-available/.rodnik-geos-restore.XXXXXXXX")" || return 1
        cp -p "$work_dir/previous-rodnik-geos.ini" "$restore_file" || return 1
        mv "$restore_file" "$ini_file" || return 1
    else
        rm -f "$ini_file" || return 1
    fi
    if [[ "$had_cli_link" == false ]]; then
        rm -f "$php_root/cli/conf.d/20-rodnik-geos.ini" || return 1
    fi
    if [[ "$had_fpm_link" == false ]]; then
        rm -f "$php_root/fpm/conf.d/20-rodnik-geos.ini" || return 1
    fi
}

on_exit() {
    local exit_status=$?
    trap - EXIT
    set +e
    if [[ "$exit_status" -ne 0 && "$activation_started" == true && "$configuration_snapshot_taken" == true ]]; then
        if rollback_configuration; then
            printf '%s\n' 'GEOS activation failed; the previous INI and links have been restored.' >&2
        else
            printf 'GEOS configuration rollback failed; inspect %s and backup %s before reloading FPM.\n' "$ini_file" "$work_dir" >&2
            work_dir=""
        fi
        if [[ "$fpm_reload_attempted" == true ]]; then
            printf '%s\n' 'An FPM reload was attempted. Check php-fpm8.5 -t and reload the restored configuration manually.' >&2
        fi
        printf '%s\n' 'Installed apt packages and immutable compiled modules have been retained.' >&2
    fi
    cleanup
    exit "$exit_status"
}

build_extension() {
    apt-get update
    DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=l apt-get install -y --no-install-recommends \
        -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
        libgeos-dev php8.5-dev build-essential autoconf pkg-config curl ca-certificates
    validate_runtime
    validate_build_tools

    curl --fail --show-error --location --proto '=https' --proto-redir '=https' \
        --connect-timeout 20 --max-time 300 --retry 3 \
        --output "$work_dir/source.tar.gz" "$archive_url"
    verify_checksum "$work_dir/source.tar.gz"
    mkdir "$work_dir/source"
    tar -xzf "$work_dir/source.tar.gz" -C "$work_dir/source" --strip-components=1
    (
        cd "$work_dir/source"
        "$phpize_bin"
        ./configure --enable-geos \
            --with-php-config="$php_config_bin" \
            --with-geos-config=/usr/bin/geos-config
        make -j2
    )
    smoke_test "$work_dir/source/modules/geos.so" || fail 'The candidate GEOS module failed its smoke tests; it was not activated.'

    local module_directory
    install -d -m 0755 "$extension_dir/rodnik-geos/$source_commit"
    module_directory="$(mktemp -d "$extension_dir/rodnik-geos/$source_commit/build.XXXXXXXX")"
    chmod 0755 "$module_directory"
    install -m 0644 "$work_dir/source/modules/geos.so" "$module_directory/geos.so.new"
    mv "$module_directory/geos.so.new" "$module_directory/geos.so"
    module_path="$module_directory/geos.so"
}

activate_extension() {
    local ini_temp sapi link
    [[ -f "$module_path" ]] || fail 'No compiled GEOS module is available to activate.'
    activation_started=true
    if ! cmp -s "$ini_file" <(ini_contents "$module_path"); then
        ini_temp="$(mktemp "$php_root/mods-available/.rodnik-geos.XXXXXXXX")"
        ini_contents "$module_path" > "$ini_temp"
        chmod 0644 "$ini_temp"
        mv "$ini_temp" "$ini_file"
    fi
    for sapi in cli fpm; do
        link="$php_root/$sapi/conf.d/20-rodnik-geos.ini"
        if [[ ! -e "$link" && ! -L "$link" ]]; then
            ln -s "$ini_file" "$link"
        fi
    done
}

verify_activation() {
    smoke_test || fail 'GEOS failed with the installed CLI configuration; FPM was not reloaded.'
    verify_fpm_extension
    "$fpm_bin" -t
    if systemctl is-active --quiet php8.5-fpm; then
        fpm_reload_attempted=true
        systemctl reload php8.5-fpm
        printf '%s\n' 'Active PHP 8.5 FPM service reloaded.'
    else
        printf '%s\n' 'PHP 8.5 FPM is inactive; it was not started. Its next start will load GEOS.'
    fi
    printf '%s\n' \
        "GEOS installed: $module_path" \
        "Configuration: $ini_file (CLI and FPM)." \
        'Restart queue workers, tiles:daemon and any other long-running PHP processes through Forge.'
}

install_extension() {
    unset PHPRC PHP_INI_SCAN_DIR
    preflight
    work_dir="$(mktemp -d /tmp/rodnik-geos.XXXXXXXX)"
    trap on_exit EXIT
    if [[ -n "$existing_module" ]] && smoke_test "$existing_module"; then
        module_path="$existing_module"
        printf '%s\n' 'Reusing the existing managed GEOS module; no packages or binaries will be replaced.'
    else
        build_extension
    fi
    snapshot_configuration
    activate_extension
    verify_activation
}

main() {
    if [[ $# -gt 1 ]]; then
        printf 'Unexpected arguments: %s\n' "$*" >&2
        usage >&2
        return 2
    fi
    case "${1:---plan}" in
        --help|-h) usage ;;
        --plan) print_plan ;;
        --execute) install_extension ;;
        *) printf 'Unknown argument: %s\n' "$1" >&2; usage >&2; return 2 ;;
    esac
}

if [[ "${BASH_SOURCE[0]:-$0}" == "$0" ]]; then
    main "$@"
fi
