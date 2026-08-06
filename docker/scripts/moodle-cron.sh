#!/usr/bin/env bash
set -Eeuo pipefail

MOODLE_DIR="${MOODLE_DIR:-/var/www/moodle}"
MOODLE_DATA_DIR="${MOODLE_DATA_DIR:-/var/www/moodledata}"
HEARTBEAT="${MOODLE_DATA_DIR}/.nexus-cron-heartbeat"

while true; do
    echo "[$(date -Iseconds)] Running Moodle cron."
    # Moodle 4.5 otherwise keeps one CLI process alive for its default 180 seconds.
    # A zero keep-alive makes this container run a discrete cron pass every minute.
    if runuser -u www-data -- php "${MOODLE_DIR}/admin/cli/cron.php" --keep-alive=0; then
        touch "${HEARTBEAT}"
        chown www-data:www-data "${HEARTBEAT}"
    else
        echo "[$(date -Iseconds)] Moodle cron failed; retrying in one minute." >&2
    fi
    sleep 60
done
