#!/usr/bin/env bash

set -Eeuo pipefail

development_root="/home/ilya/ilya.rodnik.today"
stage_root="$development_root/storage/dumps/production-refresh"
backup_root="$development_root/storage/dumps/development-backups"

usage() {
    printf '%s\n' \
        "Usage: restore-production-data.sh --execute STAGE_NAME" \
        "" \
        "Run on ilya.rodnik.today after staging has completed." \
        "An exact stage name is required; there is no implicit latest stage."
}

if [[ "${1:-}" != "--execute" || -z "${2:-}" || -n "${3:-}" ]]; then
    usage >&2
    exit 2
fi

stage_name="$2"
[[ "$stage_name" =~ ^production-[0-9]{8}T[0-9]{6}Z$ ]] || {
    echo "Invalid stage name: $stage_name" >&2
    exit 2
}

cd "$development_root"
[[ "$(id -un)" == "ilya" ]] || {
    echo "This script must run as the ilya user." >&2
    exit 1
}
[[ "$(git branch --show-current)" == "ilya" ]] || {
    echo "Refusing to restore outside the ilya branch." >&2
    exit 1
}

stage_path="$stage_root/$stage_name"
database_dump="$stage_path/database.sql.gz"
staged_photos="$stage_path/storage/app/photos"
staged_profile_photos="$stage_path/storage/app/public/profile-photos"

for required_path in \
    "$database_dump" \
    "$stage_path/manifest.txt" \
    "$staged_photos" \
    "$staged_profile_photos"; do
    [[ -e "$required_path" ]] || {
        echo "Incomplete stage; missing: $required_path" >&2
        exit 1
    }
done

expected_sha256="$(sed -n 's/^database_sha256=//p' "$stage_path/manifest.txt")"
actual_sha256="$(sha256sum "$database_dump" | cut -d ' ' -f 1)"
[[ -n "$expected_sha256" && "$actual_sha256" == "$expected_sha256" ]] || {
    echo "Database dump checksum mismatch." >&2
    exit 1
}
gzip -t "$database_dump"

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
[[ "$db_name" == "ilya" ]] || {
    echo "Refusing to restore unexpected database: $db_name" >&2
    exit 1
}

backup_name="before-$stage_name"
backup_path="$backup_root/$backup_name"
[[ ! -e "$backup_path" ]] || {
    echo "Backup path already exists: $backup_path" >&2
    exit 1
}
install -d -m 0770 "$backup_path"

echo "Backing up the current development database..."
MYSQL_PWD="$db_password" mysqldump \
    --host="$db_host" --port="$db_port" --user="$db_user" \
    --single-transaction --quick --skip-lock-tables --hex-blob \
    --set-gtid-purged=OFF --no-tablespaces \
    --default-character-set=utf8mb4 "$db_name" \
    | gzip -1 > "$backup_path/database.sql.gz"
gzip -t "$backup_path/database.sql.gz"

echo "Putting development into maintenance mode..."
php artisan down --retry=60

restore_failed=true
on_exit() {
    if [[ "$restore_failed" == true ]]; then
        echo "Restore failed. Development remains in maintenance mode." >&2
        echo "Database backup: $backup_path/database.sql.gz" >&2
        echo "Previous photos, if moved, are under: $backup_path" >&2
    fi
}
trap on_exit EXIT

echo "Restoring database $db_name..."
gzip -dc "$database_dump" \
    | MYSQL_PWD="$db_password" mysql \
        --host="$db_host" --port="$db_port" --user="$db_user" \
        --default-character-set=utf8mb4 "$db_name"

echo "Applying migrations present on the ilya branch..."
php artisan migrate --force

echo "Invalidating copied sessions, tokens, password resets, and queued jobs..."
MYSQL_PWD="$db_password" mysql \
    --host="$db_host" --port="$db_port" --user="$db_user" "$db_name" <<'SQL'
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE TABLE sessions;
TRUNCATE TABLE personal_access_tokens;
TRUNCATE TABLE password_resets;
TRUNCATE TABLE jobs;
TRUNCATE TABLE failed_jobs;
SET FOREIGN_KEY_CHECKS=1;
SQL

echo "Swapping the staged photo directories into place..."
if [[ -e storage/app/photos ]]; then
    mv storage/app/photos "$backup_path/photos"
fi
if [[ -e storage/app/public/profile-photos ]]; then
    mv storage/app/public/profile-photos "$backup_path/profile-photos"
fi
mv "$staged_photos" storage/app/photos
mv "$staged_profile_photos" storage/app/public/profile-photos
chmod 0775 storage/app/photos storage/app/public/profile-photos

php artisan storage:link --force
php artisan optimize:clear

echo "Verifying database and files..."
php artisan migrate:status --no-ansi >/dev/null
php artisan db:show --counts --no-ansi

database_photo_count="$(MYSQL_PWD="$db_password" mysql \
    --batch --skip-column-names \
    --host="$db_host" --port="$db_port" --user="$db_user" "$db_name" \
    -e 'SELECT COUNT(*) FROM photos;')"
file_photo_count="$(find storage/app/photos -type f -print | wc -l)"
profile_photo_count="$(find storage/app/public/profile-photos -type f -print | wc -l)"
missing_photo_count=0
missing_profile_photo_count=0

while IFS= read -r photo_filename; do
    if [[ ! -f "storage/app/photos/$photo_filename" ]]; then
        echo "Missing report photo: $photo_filename" >&2
        ((missing_photo_count += 1))
    fi
done < <(MYSQL_PWD="$db_password" mysql \
    --batch --skip-column-names \
    --host="$db_host" --port="$db_port" --user="$db_user" "$db_name" \
    -e 'SELECT CONCAT(id, ".", extension) FROM photos;')

while IFS= read -r profile_photo_path; do
    if [[ ! -f "storage/app/public/$profile_photo_path" ]]; then
        echo "Missing profile photo: $profile_photo_path" >&2
        ((missing_profile_photo_count += 1))
    fi
done < <(MYSQL_PWD="$db_password" mysql \
    --batch --skip-column-names \
    --host="$db_host" --port="$db_port" --user="$db_user" "$db_name" \
    -e 'SELECT profile_photo_path FROM users WHERE profile_photo_path IS NOT NULL;')

[[ "$file_photo_count" -ge "$database_photo_count" ]] || {
    echo "Photo verification failed: database=$database_photo_count files=$file_photo_count" >&2
    exit 1
}
[[ "$missing_photo_count" -eq 0 && "$missing_profile_photo_count" -eq 0 ]] || {
    echo "Referenced photo verification failed: report=$missing_photo_count profile=$missing_profile_photo_count" >&2
    exit 1
}

php artisan up
restore_failed=false
trap - EXIT

printf '%s\n' \
    "Restore completed successfully." \
    "Database photo rows: $database_photo_count" \
    "Report photo files: $file_photo_count" \
    "Profile photo files: $profile_photo_count" \
    "Rollback data: $backup_path"
