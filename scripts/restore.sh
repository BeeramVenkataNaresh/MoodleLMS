#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

backup_dir="${1:-}"
if [[ -z "${backup_dir}" || ! -d "${backup_dir}" ]]; then
    echo "Usage: ./scripts/restore.sh /absolute/path/to/nexus-moodle-YYYYMMDD-HHMMSS --yes"
    exit 1
fi

if [[ "${2:-}" != "--yes" ]]; then
    echo "Restore replaces the current Nexus Moodle application, moodledata, MariaDB, and Redis volumes."
    echo "Review the backup path, then rerun with --yes."
    exit 1
fi

for required_file in moodle.sql moodle-application.tar.gz moodledata.tar.gz; do
    [[ -f "${backup_dir}/${required_file}" ]] || { echo "Missing ${required_file} in backup."; exit 1; }
done

[[ -f .env ]] || { echo "Missing .env. Restore needs local database credentials."; exit 1; }

docker compose down --volumes --remove-orphans
docker volume create nexus-eps-lms_moodle_app >/dev/null
docker volume create nexus-eps-lms_moodledata >/dev/null

echo "Restoring Moodle application and moodledata volumes..."
docker run --rm \
    -v nexus-eps-lms_moodle_app:/target \
    -v "$(cd "${backup_dir}" && pwd):/backup:ro" \
    alpine:3.20 \
    tar -C /target -xzf /backup/moodle-application.tar.gz
docker run --rm \
    -v nexus-eps-lms_moodledata:/target \
    -v "$(cd "${backup_dir}" && pwd):/backup:ro" \
    alpine:3.20 \
    tar -C /target -xzf /backup/moodledata.tar.gz

docker compose up -d --wait db redis mailpit
echo "Restoring MariaDB..."
docker compose exec -T db sh -ec \
    'exec mariadb --default-character-set=utf8mb4 -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' \
    < "${backup_dir}/moodle.sql"

docker compose up -d
echo "Restore complete. Run docker compose ps and open http://localhost:8080."
