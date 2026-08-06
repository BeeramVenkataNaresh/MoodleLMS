#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [[ ! -f .env ]]; then
    echo "Missing .env; cannot identify the local database credentials."
    exit 1
fi

set -a
source ./.env
set +a

timestamp="$(date +%Y%m%d-%H%M%S)"
backup_dir="${ROOT_DIR}/backups/nexus-moodle-${timestamp}"
mkdir -p "${backup_dir}/config"

for service in db moodle; do
    if [[ -z "$(docker compose ps -q "${service}")" ]]; then
        echo "${service} is not running. Start the stack before creating a backup."
        exit 1
    fi
done

echo "Exporting MariaDB..."
docker compose exec -T db sh -ec \
    'exec mariadb-dump --single-transaction --routines --events --triggers --default-character-set=utf8mb4 -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' \
    > "${backup_dir}/moodle.sql"

echo "Archiving Moodle application code, themes, and plugins..."
docker run --rm \
    -v nexus-eps-lms_moodle_app:/source:ro \
    -v "${backup_dir}:/backup" \
    alpine:3.20 \
    tar -C /source -czf /backup/moodle-application.tar.gz .

echo "Archiving moodledata..."
docker run --rm \
    -v nexus-eps-lms_moodledata:/source:ro \
    -v "${backup_dir}:/backup" \
    alpine:3.20 \
    tar -C /source -czf /backup/moodledata.tar.gz .

cp docker-compose.yml Dockerfile .env.example README.md "${backup_dir}/config/"
cp -R docker "${backup_dir}/config/docker"
awk -F= '
    /^(MYSQL_ROOT_PASSWORD|MOODLE_DB_PASSWORD|REDIS_PASSWORD|MOODLE_ADMIN_PASSWORD)=/ {
        print $1 "=<redacted>";
        next;
    }
    { print }
' .env > "${backup_dir}/config/.env.redacted"

(
    echo "Created: $(date -Iseconds)"
    echo "Moodle source volume: nexus-eps-lms_moodle_app"
    echo "Moodledata volume: nexus-eps-lms_moodledata"
    echo "Database: ${MOODLE_DB_NAME}"
    echo "Image: $(docker image inspect --format '{{index .RepoDigests 0}}' nexus-eps-moodle:4.5-php8.3 2>/dev/null || true)"
) > "${backup_dir}/manifest.txt"

(cd "${backup_dir}" && shasum -a 256 moodle.sql moodle-application.tar.gz moodledata.tar.gz manifest.txt > SHA256SUMS)

echo "Backup created: ${backup_dir}"
