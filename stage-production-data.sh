#!/usr/bin/env bash

set -Eeuo pipefail

production_ssh="rodnik@rodnik.today"
development_ssh="ilya@ilya.rodnik.today"
production_root="/home/rodnik/rodnik.today"
development_root="/home/ilya/ilya.rodnik.today"
ssh_options=(-o BatchMode=yes -o ConnectTimeout=10)

usage() {
    printf '%s\n' \
        "Usage: stage-production-data.sh [--execute]" \
        "" \
        "Run on an operator machine that can SSH to both servers." \
        "Without --execute, only connectivity and prerequisites are checked." \
        "With --execute, data is staged on development but is not restored."
}

execute=false
case "${1:-}" in
    "") ;;
    --execute) execute=true ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; exit 2 ;;
esac

for command_name in ssh gzip; do
    command -v "$command_name" >/dev/null 2>&1 || {
        echo "Required command is missing: $command_name" >&2
        exit 1
    }
done

echo "Checking SSH access and project paths..."
ssh "${ssh_options[@]}" "$production_ssh" "test -f '$production_root/artisan'"
ssh "${ssh_options[@]}" "$development_ssh" \
    "test -f '$development_root/artisan' && test -w '$development_root/storage/dumps'"

stage_name="production-$(date -u +%Y%m%dT%H%M%SZ)"
stage_path="$development_root/storage/dumps/production-refresh/$stage_name"

echo "Production source:  $production_ssh:$production_root"
echo "Development stage: $development_ssh:$stage_path"
echo "Content: database, report photos, and profile photos"
echo "Generated tiles and exports are intentionally excluded."

if [[ "$execute" != true ]]; then
    echo "Dry run only. Re-run with --execute to transfer data."
    exit 0
fi

ssh "${ssh_options[@]}" "$development_ssh" "install -d -m 0770 '$stage_path'"

echo "Streaming the production database dump..."
ssh "${ssh_options[@]}" "$production_ssh" 'bash -s' <<'PRODUCTION_DUMP' \
    | gzip -1 \
    | ssh "${ssh_options[@]}" "$development_ssh" "umask 0077; cat > '$stage_path/database.sql.gz'"
set -Eeuo pipefail
cd /home/rodnik/rodnik.today

env_value() {
    php -r '
        $values = parse_ini_file($argv[1], false, INI_SCANNER_RAW);
        if (! is_array($values) || ! array_key_exists($argv[2], $values)) {
            fwrite(STDERR, "Missing environment key: {$argv[2]}\n");
            exit(1);
        }
        echo $values[$argv[2]];
    ' .env "$1"
}

db_host="$(env_value DB_HOST)"
db_port="$(env_value DB_PORT)"
db_name="$(env_value DB_DATABASE)"
db_user="$(env_value DB_USERNAME)"
db_password="$(env_value DB_PASSWORD)"

MYSQL_PWD="$db_password" exec mysqldump \
    --host="$db_host" --port="$db_port" --user="$db_user" \
    --single-transaction --quick --skip-lock-tables --hex-blob \
    --set-gtid-purged=OFF --no-tablespaces \
    --default-character-set=utf8mb4 "$db_name"
PRODUCTION_DUMP

echo "Streaming report and profile photos..."
ssh "${ssh_options[@]}" "$production_ssh" \
    "cd '$production_root' && exec tar -cf - storage/app/photos storage/app/public/profile-photos" \
    | ssh "${ssh_options[@]}" "$development_ssh" "cd '$stage_path' && exec tar -xf -"

production_photo_count="$(ssh "${ssh_options[@]}" "$production_ssh" \
    "find '$production_root/storage/app/photos' -type f -print | wc -l")"
production_profile_count="$(ssh "${ssh_options[@]}" "$production_ssh" \
    "find '$production_root/storage/app/public/profile-photos' -type f -print | wc -l")"

ssh "${ssh_options[@]}" "$development_ssh" "bash -s" <<DEVELOPMENT_MANIFEST
set -Eeuo pipefail
cd "$stage_path"
staged_photo_count="\$(find storage/app/photos -type f -print | wc -l)"
staged_profile_count="\$(find storage/app/public/profile-photos -type f -print | wc -l)"
[[ "\$staged_photo_count" -eq "$production_photo_count" ]] || {
    echo "Report photo count mismatch: production=$production_photo_count staged=\$staged_photo_count" >&2
    exit 1
}
[[ "\$staged_profile_count" -eq "$production_profile_count" ]] || {
    echo "Profile photo count mismatch: production=$production_profile_count staged=\$staged_profile_count" >&2
    exit 1
}
gzip -t database.sql.gz
database_sha256="\$(sha256sum database.sql.gz | cut -d ' ' -f 1)"
{
    echo "stage_name=$stage_name"
    echo "created_at_utc=\$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "production_host=rodnik.today"
    echo "database_sha256=\$database_sha256"
    echo "report_photo_files=\$staged_photo_count"
    echo "profile_photo_files=\$staged_profile_count"
} > manifest.txt
chmod 0600 database.sql.gz manifest.txt
du -sh database.sql.gz storage/app/photos storage/app/public/profile-photos
cat manifest.txt
DEVELOPMENT_MANIFEST

echo "Staging completed. Nothing has been restored."
echo "Review: ssh $development_ssh 'cd $stage_path && cat manifest.txt'"
echo "Restore: ssh $development_ssh 'cd $development_root && ./restore-production-data.sh --execute $stage_name'"
