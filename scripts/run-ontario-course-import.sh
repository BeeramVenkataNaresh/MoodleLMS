#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CSV_INPUT="${1:-}"

cd "$PROJECT_ROOT"

if [[ -z "$CSV_INPUT" ]]; then
    echo "Usage:"
    echo "  ./scripts/run-ontario-course-import.sh scripts/ministry-data/courses.csv"
    exit 1
fi

if [[ "$CSV_INPUT" = /* ]]; then
    CSV_FILE="$CSV_INPUT"
else
    CSV_FILE="$PROJECT_ROOT/$CSV_INPUT"
fi

if [[ ! -f "$CSV_FILE" ]]; then
    echo "CSV file not found:"
    echo "  $CSV_FILE"
    exit 1
fi

IMPORTER="$PROJECT_ROOT/scripts/import-ontario-courses-csv.php"
REPAIR_SCRIPT="$PROJECT_ROOT/scripts/repair-course-categories-metadata.php"
ENROL_SCRIPT="$PROJECT_ROOT/scripts/enrol-admin-all-courses.php"

if [[ ! -f "$IMPORTER" ]]; then
    echo "Importer not found: $IMPORTER"
    exit 1
fi

echo
echo "Nexus EPS — Ontario Course Import"
echo "=================================="
echo "CSV: $CSV_FILE"
echo

echo "1/7 Creating backup..."
"$PROJECT_ROOT/scripts/backup.sh"

echo "2/7 Copying importer and CSV..."
docker compose cp \
    "$IMPORTER" \
    moodle:/tmp/import-ontario-courses-csv.php

docker compose cp \
    "$CSV_FILE" \
    moodle:/tmp/ontario-courses.csv

echo "3/7 Importing courses..."
docker compose exec -T moodle \
    php /tmp/import-ontario-courses-csv.php \
    --file=/tmp/ontario-courses.csv

if [[ -f "$REPAIR_SCRIPT" ]]; then
    echo "4/7 Repairing course categories and metadata..."

    docker compose cp \
        "$REPAIR_SCRIPT" \
        moodle:/tmp/repair-course-categories-metadata.php

    docker compose exec -T moodle \
        php /tmp/repair-course-categories-metadata.php
else
    echo "4/7 Repair script not found; skipping."
fi

if [[ -f "$ENROL_SCRIPT" ]]; then
    echo "5/7 Enrolling admin in all courses..."

    docker compose cp \
        "$ENROL_SCRIPT" \
        moodle:/tmp/enrol-admin-all-courses.php

    docker compose exec -T moodle \
        php /tmp/enrol-admin-all-courses.php admin
else
    echo "5/7 Enrolment script not found; skipping."
fi

echo "6/7 Purging Moodle caches..."
docker compose exec -T moodle \
    php admin/cli/purge_caches.php

echo "7/7 Restarting Moodle services..."
docker compose restart moodle moodle-cron

echo
echo "Import completed successfully."
echo "Courses remain hidden pending Nexus review."
